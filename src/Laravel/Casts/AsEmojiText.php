<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Casts;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Stores free text with no four-byte characters — emoji become `:shortcode:` — and reads it back exactly as
 * written. For columns whose charset cannot hold them (MySQL utf8mb3), or where search should match ":rocket:".
 *
 *     protected function casts(): array { return ['bio' => AsEmojiText::class]; }
 *
 * The stored form is a small escaped format, so that every value round-trips:
 *
 * | Written          | Stored        |
 * |------------------|---------------|
 * | an emoji         | `:rocket:` — its ASCII code, which always resolves back to that emoji |
 * | `:` typed        | `\:`          |
 * | `\` typed        | `\\`          |
 * | any other character above U+FFFF (an emoji newer than the dataset, a rare CJK ideograph) | `:U+1FAE8:` |
 *
 * So an unescaped `:code:` in storage is always one the cast wrote, and is read back wherever it sits —
 * `a🚀b` is stored as `a:rocket:b` and reads as `a🚀b`. The delimiters are fixed, whatever
 * `shortcodes.delimiters` says, because they are part of the stored format.
 *
 * Rows written before 0.2 (emoji as shortcodes, nothing escaped) read back as before, except that a
 * shortcode touching a letter is now converted too.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class AsEmojiText implements CastsAttributes
{
    public static function encode(string $text, Emojis $emojis): string
    {
        $out = '';
        $cursor = 0;

        foreach ($emojis->text($text)->from(Mode::Unicode)->extract() as $match) {
            if (! $match->emoji instanceof Emoji) {
                continue;
            }

            $out .= self::escape(substr($text, $cursor, $match->offset - $cursor)) . ':' . $match->emoji->asciiCode . ':';
            $cursor = $match->offset + $match->length;
        }

        return $out . self::escape(substr($text, $cursor));
    }

    public static function decode(string $stored, Emojis $emojis): string
    {
        $out = '';
        $length = strlen($stored);
        $i = 0;

        while ($i < $length) {
            // Copy everything up to the next character that means something in the format.
            $plain = strcspn($stored, '\\:', $i);
            $out .= substr($stored, $i, $plain);
            $i += $plain;

            if ($i >= $length) {
                break;
            }

            $next = $stored[$i + 1] ?? '';

            if ($stored[$i] === '\\') {
                // An escape; a backslash before anything else is a literal backslash (rows written before 0.2).
                $out .= $next === '\\' || $next === ':' ? $next : '\\';
                $i += $next === '\\' || $next === ':' ? 2 : 1;

                continue;
            }

            if (preg_match('/\G:(U\+[0-9A-F]{4,6}|[a-z0-9_+\-]+):/', $stored, $m, 0, $i) === 1 && ($char = self::resolve($m[1], $emojis)) !== null) {
                $out .= $char;
                $i += strlen($m[0]);

                continue;
            }

            // A colon that opens no code: literal, and the scan resumes right after it, so ":a:rocket:" still
            // finds the rocket.
            $out .= ':';
            $i++;
        }

        return $out;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) ? self::decode($value, app(Emojis::class)) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) ? self::encode($value, app(Emojis::class)) : null;
    }

    private static function resolve(string $code, Emojis $emojis): ?string
    {
        if (str_starts_with($code, 'U+')) {
            $codepoint = (int) hexdec(substr($code, 2));

            return $codepoint > 0xFFFF && $codepoint <= 0x10FFFF ? mb_chr($codepoint, 'UTF-8') : null;
        }

        $emoji = $emojis->catalogue()->byShortcode($code) ?? $emojis->catalogue()->bySlug($code);

        return $emoji instanceof Emoji ? $emoji->char : null;
    }

    /** Escape the format's two special characters, and write any four-byte character as `:U+…:`. */
    private static function escape(string $text): string
    {
        $text = strtr($text, ['\\' => '\\\\', ':' => '\\:']);

        return (string) preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', static fn (array $m): string => sprintf(':U+%X:', mb_ord($m[0], 'UTF-8')), $text);
    }
}
