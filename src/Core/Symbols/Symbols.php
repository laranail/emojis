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
        $members = $this->data->symbols()['groups'][$name] ?? '';

        return $members === '' ? [] : array_values(array_filter(array_map($this->byHex(...), explode(' ', $members)), static fn (?Symbol $s): bool => $s instanceof Symbol));
    }

    /** By the character itself, `U+2192`, or the hex code point. */
    public function get(string $key): ?Symbol
    {
        $key = trim($key);

        if (preg_match('/^(?:U\+)?([0-9A-Fa-f]{4,6})$/', $key, $m) === 1) {
            return $this->byHex(strtoupper(ltrim($m[1], '0') === '' ? '0' : $m[1]));
        }

        return mb_strlen($key, 'UTF-8') === 1 ? $this->byHex(sprintf('%04X', mb_ord($key, 'UTF-8'))) : null;
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

        $pool = $group === null ? array_keys($this->data->symbols()['symbols']) : explode(' ', $this->data->symbols()['groups'][$group] ?? '');
        $phrase = implode(' ', $words);
        $exact = [];
        $first = [];
        $rest = [];

        foreach ($pool as $hex) {
            $record = $this->data->symbols()['symbols'][$hex] ?? null;

            if ($record === null) {
                continue;
            }

            foreach ($words as $word) {
                if (! str_contains($record[0], $word)) {
                    continue 2;
                }
            }

            match (true) {
                $record[0] === $phrase                 => $exact[] = (string) $hex,
                str_starts_with($record[0], $words[0]) => $first[] = (string) $hex,
                default                                => $rest[] = (string) $hex,
            };
        }

        return array_values(array_filter(array_map($this->byHex(...), array_slice([...$exact, ...$first, ...$rest], 0, $limit)), static fn (?Symbol $s): bool => $s instanceof Symbol));
    }

    public function count(): int
    {
        return count($this->data->symbols()['symbols']);
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

        return $this->built[$hex] = new Symbol((int) hexdec($hex), $record[0], $record[1], $shard['blocks'][$record[2]] ?? '', $record[3]);
    }
}
