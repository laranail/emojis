<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Symbols;

use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;

/**
 * The catalogue of special characters that are not emoji, in groups: popular, arrows, currency, math,
 * numbers, punctuation, letters, symbols and hieroglyphs. Built from Unicode's own character database and
 * the WHATWG entity list; see docs/tools/symbols.md for how each group is defined.
 */
final class Symbols
{
    /** Positions in a symbol record, in the order of the shard's `fields` (pinned by SymbolsTest). */
    private const int CHAR = 0;

    private const int NAME = 1;

    private const int CATEGORY = 2;

    private const int BLOCK = 3;

    private const int ENTITY = 4;

    /** @var array<string, Symbol> */
    private array $built = [];

    public function __construct(private readonly DatasetStore $data) {}

    /** @return list<string> group names, popular first */
    public function groups(): array
    {
        return array_keys($this->data->symbols()['groups']);
    }

    /** @return list<Symbol> the group's members in code point order (popular in curated order); [] for an unknown group */
    public function group(string $name): array
    {
        return array_values(array_filter(array_map(fn (string $char): ?Symbol => $this->byHex($this->hexOf($char)), $this->characters($name)), static fn (?Symbol $s): bool => $s instanceof Symbol));
    }

    /**
     * The group's characters themselves, in the same order as group(): what a picker or a "copy all" button
     * needs, without building a Symbol for each. [] for an unknown group.
     *
     * @return list<string>
     */
    public function characters(string $name): array
    {
        $members = $this->data->symbols()['groups'][$name] ?? '';

        return $members === '' ? [] : explode(' ', $members);
    }

    /** @return list<Symbol> every symbol, in code point order */
    public function all(): array
    {
        return array_values(array_filter(array_map(fn (int|string $hex): ?Symbol => $this->byHex((string) $hex), array_keys($this->data->symbols()['symbols'])), static fn (?Symbol $s): bool => $s instanceof Symbol));
    }

    /** By the character itself, `U+2192`, or the hex code point. */
    public function get(string $key): ?Symbol
    {
        $key = trim($key);

        // A code point in any spelling ("2192", "U+02192", "u+2192"), keyed the way the dataset is: %04X.
        if (preg_match('/^(?:[Uu]\+)?([0-9A-Fa-f]{4,6})$/', $key, $m) === 1) {
            return $this->byHex(sprintf('%04X', hexdec($m[1])));
        }

        return mb_strlen($key, 'UTF-8') === 1 ? $this->byHex($this->hexOf($key)) : null;
    }

    /**
     * Symbols whose name contains every word of the query: an exact name first, then name-prefix matches.
     *
     * @return list<Symbol>
     */
    public function search(string $query, ?string $group = null, int $limit = 50): array
    {
        $words = array_values(array_filter(explode(' ', strtolower(trim((string) preg_replace('/\s+/', ' ', $query)))), static fn (string $word): bool => $word !== ''));

        if ($words === [] || $limit < 1) {
            return [];
        }

        $records = $this->data->symbols()['symbols'];
        $pool = $group === null ? array_keys($records) : array_map($this->hexOf(...), $this->characters($group));
        $phrase = implode(' ', $words);
        $exact = [];
        $first = [];
        $rest = [];

        foreach ($pool as $hex) {
            $name = $records[$hex][self::NAME] ?? null;

            if ($name === null) {
                continue;
            }

            foreach ($words as $word) {
                if (! str_contains($name, $word)) {
                    continue 2;
                }
            }

            match (true) {
                $name === $phrase                 => $exact[] = (string) $hex,
                str_starts_with($name, $words[0]) => $first[] = (string) $hex,
                default                           => $rest[] = (string) $hex,
            };
        }

        return array_values(array_filter(array_map($this->byHex(...), array_slice([...$exact, ...$first, ...$rest], 0, $limit)), static fn (?Symbol $s): bool => $s instanceof Symbol));
    }

    public function count(): int
    {
        return count($this->data->symbols()['symbols']);
    }

    /** The record key for a character: its code point in upper-case hex, at least four digits. */
    private function hexOf(string $char): string
    {
        return sprintf('%04X', mb_ord($char, 'UTF-8'));
    }

    private function byHex(string $hex): ?Symbol
    {
        if (isset($this->built[$hex])) {
            return $this->built[$hex];
        }

        $shard = $this->data->symbols();
        $record = $shard['symbols'][$hex] ?? null;

        if ($record === null) {
            return null;
        }

        return $this->built[$hex] = new Symbol(mb_ord($record[self::CHAR], 'UTF-8'), $record[self::NAME], $record[self::CATEGORY], $shard['blocks'][$record[self::BLOCK]] ?? '', $record[self::ENTITY]);
    }
}
