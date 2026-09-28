<?php

declare(strict_types=1);

/**
 * Renders PHP values as source in the shape laranail's Pint config produces: short arrays, four-space
 * indentation, trailing commas, `=>` separated by single spaces.
 *
 * var_export() cannot be used for the dataset. It writes emoji, zero-width joiners, variation selectors and
 * tag characters as raw bytes, so a diff of the data shows invisible changes and an editor can silently
 * normalise them. Those are written double-quoted with `\u{…}` escapes instead; letters, digits and
 * punctuation of every script stay raw, so localized names remain readable and the shards stay small.
 */
final class PhpEmitter
{
    public static function file(mixed $value, string $docblock): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\n/**\n" . self::comment($docblock) . " */\nreturn "
            . self::value($value, 0) . ";\n";
    }

    public static function value(mixed $value, int $depth): string
    {
        return match (true) {
            is_array($value)  => self::array($value, $depth),
            is_string($value) => self::string($value),
            is_int($value)    => (string) $value,
            is_float($value)  => var_export($value, true),
            is_bool($value)   => $value ? 'true' : 'false',
            $value === null   => 'null',
            default           => throw new InvalidArgumentException('Unsupported value type ' . get_debug_type($value)),
        };
    }

    public static function string(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        }

        $needsEscape = false;

        foreach (mb_str_split($value, 1, 'UTF-8') as $char) {
            if (! self::isReadable($char)) {
                $needsEscape = true;

                break;
            }
        }

        // Pint's single_quote rule: a string with nothing to escape is single-quoted, whatever its script.
        if (! $needsEscape) {
            return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        }

        $out = '';

        foreach (mb_str_split($value, 1, 'UTF-8') as $char) {
            $out .= match (true) {
                $char === '\\'          => '\\\\',
                $char === '"'           => '\\"',
                $char === '$'           => '\\$',
                self::isReadable($char) => $char,
                default                 => sprintf('\u{%X}', mb_ord($char, 'UTF-8')),
            };
        }

        return '"' . $out . '"';
    }

    /**
     * Letters, digits, marks and punctuation of any script, and printable ASCII, stay raw; anything invisible,
     * spacing or pictographic (every emoji, ZWJ, variation selector, keycap, tag) is escaped.
     */
    private static function isReadable(string $char): bool
    {
        $cp = mb_ord($char, 'UTF-8');

        if ($cp >= 0x20 && $cp <= 0x7E) {
            return true;
        }

        return preg_match('/^[\p{L}\p{N}\p{P}\p{M}]$/u', $char) === 1 && ($cp < 0xFE00 || $cp > 0xFE0F) && $cp !== 0x20E3 && $cp < 0xE0000;
    }

    /** @param array<array-key, mixed> $value */
    private static function array(array $value, int $depth): string
    {
        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth + 1);
        $isList = array_is_list($value);

        // A list of scalars is written on one line: it is a record or a set of values, and one line per
        // value would multiply the shard's line count for no reader's benefit.
        if ($isList && array_filter($value, 'is_array') === []) {
            return '[' . implode(', ', array_map(static fn (mixed $item): string => self::value($item, $depth + 1), $value)) . ']';
        }

        $lines = [];
        $group = [];

        // Pint's binary_operator_spaces (align_single_space_minimal) pads keys so the `=>` of consecutive
        // elements line up, measuring display columns (a wide CJK character counts two); a multi-line
        // element joins the run it starts on and then closes it. Emitting that shape directly keeps the
        // shards Pint-clean without running Pint over 14 MB of data on every regeneration.
        $flush = static function () use (&$group, &$lines, $indent): void {
            $width = max(array_map(static fn (array $entry): int => mb_strwidth($entry[0], 'UTF-8'), $group));

            foreach ($group as [$key, $rendered]) {
                $lines[] = $indent . $key . str_repeat(' ', $width - mb_strwidth($key, 'UTF-8')) . ' => ' . $rendered . ',';
            }

            $group = [];
        };

        foreach ($value as $key => $item) {
            $rendered = self::value($item, $depth + 1);

            if ($isList) {
                $lines[] = $indent . $rendered . ',';

                continue;
            }

            $group[] = [is_int($key) ? (string) $key : self::string($key), $rendered];

            if (str_contains($rendered, "\n")) {
                $flush();
            }
        }

        if ($group !== []) {
            $flush();
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $depth) . ']';
    }

    private static function comment(string $text): string
    {
        $out = '';

        foreach (explode("\n", $text) as $line) {
            $out .= rtrim(' * ' . $line) . "\n";
        }

        return $out;
    }
}
