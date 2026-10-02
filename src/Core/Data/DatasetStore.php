<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Data;

use Throwable;
use Simtabi\Laranail\Emojis\Core\Exceptions\DatasetException;

/**
 * Lazy, validated, typed access to the generated shards in database/generated.
 *
 * Each shard is a plain PHP file returning an array, so opcache keeps it as an immutable array and a
 * repeat load costs a pointer copy — where opcache runs. The CLI does not by default
 * (opcache.enable_cli=0), and there each store re-parses the shards it touches, about 7.5 MB for a
 * warmed catalogue; see packaged(). A shard is loaded the first time something needs it: converting
 * shortcodes never touches the locale data, and rendering an emoji never touches kaomoji.
 *
 * This is the one place untyped data enters the package. Each shard is checked for its keys on load
 * (and the catalogue for its record layout); the accessors below then declare the shapes the generator
 * writes, so the rest of Core is statically typed. A missing or malformed core shard is critical
 * (DatasetException): a converter running on a partial catalogue would silently leave emoji unconverted.
 * A locale shard is different — see locale().
 *
 * Hexcode keys that are all digits ("2615") come back from PHP as integers. The shards are returned as
 * loaded (copying them would forfeit opcache's immutable arrays), so callers that iterate keys cast them
 * with (string); lookups by a string key work either way.
 *
 * @phpstan-type EmojiRecord array{0: string, 1: string, 2: string, 3: string, 4: int, 5: int, 6: string, 7: string, 8: bool, 9: ?string, 10: ?string, 11: ?string, 12: bool, 13: ?string, 14: ?string, 15: ?string, 16: ?string, 17: int}
 * @phpstan-type KaomojiItem array{value: string, group: string, description: string, ascii: bool, tags: string, reading: string}
 */
final class DatasetStore
{
    private static ?self $packaged = null;

    /** @var array<string, array<string, mixed>> */
    private array $shards = [];

    /** @var array<string, array{names: array<array-key, string>, keywords: array<array-key, string>}|null> */
    private array $locales = [];

    public function __construct(private readonly string $directory) {}

    /**
     * The shipped dataset, one store per process. Its caches are write-once reads of a fixed directory,
     * so every Emojis instance can share them; a store per instance cost ~7.5 MB each without opcache,
     * which ran this package's own suite out of a 256 MB limit.
     */
    public static function packaged(): self
    {
        return self::$packaged ??= new self(dirname(__DIR__, 3) . '/database/generated');
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /** @return array<array-key, EmojiRecord> hexcode => record (see Record), in CLDR order */
    public function emojis(): array
    {
        /** @var array<array-key, EmojiRecord> $value */
        $value = $this->catalogueShard()['emojis'];

        return $value;
    }

    /** @return EmojiRecord|null */
    public function record(string $hexcode): ?array
    {
        return $this->emojis()[$hexcode] ?? null;
    }

    /** @return array<string, array{0: string, 1: int}> sequence bytes => [hexcode, Qualification value] */
    public function sequences(): array
    {
        /** @var array<string, array{0: string, 1: int}> $value */
        $value = $this->scannerShard()['sequences'];

        return $value;
    }

    /** @return list<int> the distinct sequence byte lengths, longest first */
    public function sequenceLengths(): array
    {
        /** @var list<int> $value */
        $value = $this->scannerShard()['lengths'];

        return $value;
    }

    /** A regex character class body: every code point that can start a known sequence. */
    public function startClass(): string
    {
        /** @var string $value */
        $value = $this->scannerShard()['start'];

        return $value;
    }

    /** A regex character class body: Extended_Pictographic (incl. reserved blocks) and regional indicators. */
    public function pictographicClass(): string
    {
        /** @var string $value */
        $value = $this->scannerShard()['pictographic'];

        return $value;
    }

    /** A regex character class body: characters that may take VS15/VS16 (emoji-variation-sequences.txt). */
    public function emojiVsBaseClass(): string
    {
        /** @var string $value */
        $value = $this->scannerShard()['emojiVsBases'];

        return $value;
    }

    /** A regex character class body: characters that may take VS1–VS14 (StandardizedVariants.txt). */
    public function standardizedVsBaseClass(): string
    {
        /** @var string $value */
        $value = $this->scannerShard()['standardizedVsBases'];

        return $value;
    }

    /** A regex character class body: characters meaningless outside an emoji sequence (not ZWJ). */
    public function componentClass(): string
    {
        /** @var string $value */
        $value = $this->scannerShard()['components'];

        return $value;
    }

    /** @return array<array-key, string> code => hexcode, across every preset and the curated aliases */
    public function shortcodeIndex(): array
    {
        /** @var array<array-key, string> $value */
        $value = $this->shard('shortcodes', ['presets', 'index'])['index'];

        return $value;
    }

    /** @return array<array-key, string> hexcode => space-joined codes, primary first */
    public function shortcodePreset(string $preset): array
    {
        /** @var array<string, array<array-key, string>> $presets */
        $presets = $this->shard('shortcodes', ['presets', 'index'])['presets'];

        return $presets[$preset] ?? [];
    }

    /** @return array<array-key, string> emoticon => hexcode */
    public function emoticonMap(): array
    {
        /** @var array<array-key, string> $value */
        $value = $this->emoticonShard()['map'];

        return $value;
    }

    /** @return list<string> emoticons that only match when a caller opts in */
    public function riskyEmoticons(): array
    {
        /** @var list<string> $value */
        $value = $this->emoticonShard()['risky'];

        return $value;
    }

    /** @return array<array-key, string> hexcode => primary emoticon */
    public function primaryEmoticons(): array
    {
        /** @var array<array-key, string> $value */
        $value = $this->emoticonShard()['primary'];

        return $value;
    }

    /** @return list<KaomojiItem> */
    public function kaomojiItems(): array
    {
        /** @var list<KaomojiItem> $value */
        $value = $this->shard('kaomoji', ['groups', 'items'])['items'];

        return $value;
    }

    /** @return array<string, string> slug => label */
    public function kaomojiGroups(): array
    {
        /** @var array<string, string> $value */
        $value = $this->shard('kaomoji', ['groups', 'items'])['groups'];

        return $value;
    }

    /** @return array<string, int> set name => coverage bit */
    public function imageBits(): array
    {
        /** @var array<string, int> $value */
        $value = $this->imageShard()['bits'];

        return $value;
    }

    /** @return array<string, string> set name => pinned CDN version */
    public function imageVersions(): array
    {
        /** @var array<string, string> $value */
        $value = $this->imageShard()['versions'];

        return $value;
    }

    /**
     * The measured crops of one set: hexcode => "inset x y size" in permille of the canvas — the Balanced
     * inset (per side) and the Tight square. Empty for a set that was not measured.
     *
     * @return array<array-key, string>
     */
    public function imageCrops(string $set): array
    {
        /** @var array<string, array<array-key, string>> $crops */
        $crops = $this->imageShard()['crops'] ?? [];

        return $crops[$set] ?? [];
    }

    /**
     * The SHA-256 prefix (128 bits, hex) of each image of a set at its pinned version, keyed by hexcode;
     * empty when the set has no hash manifest. Loaded only by the image installer.
     *
     * @return array<array-key, string>
     */
    public function imageHashes(string $set): array
    {
        if (preg_match('/^[a-z0-9-]+$/', $set) !== 1 || ! is_file("{$this->directory}/image-hashes/{$set}.php")) {
            return [];
        }

        /** @var array<array-key, string> $hashes */
        $hashes = $this->shard("image-hashes/{$set}", ['version', 'hashes'])['hashes'];

        return $hashes;
    }

    /**
     * The symbol catalogue: `fields`, `blocks` (names), `groups` (name => the members' characters,
     * space-separated) and `symbols` (hex => [char, name, category, block index, entity|null]).
     *
     * @return array{fields: list<string>, blocks: list<string>, groups: array<string, string>, symbols: array<array-key, array{0: string, 1: string, 2: string, 3: int, 4: ?string}>}
     */
    public function symbols(): array
    {
        /** @var array{fields: list<string>, blocks: list<string>, groups: array<string, string>, symbols: array<array-key, array{0: string, 1: string, 2: string, 3: int, 4: ?string}>} $shard */
        $shard = $this->shard('symbols', ['fields', 'blocks', 'groups', 'symbols']);

        return $shard;
    }

    /**
     * The status tags: `fields`, `groups` (slug => label), `tags` (label => [group, role, symbol, emoji chars
     * space-separated, aliases |-separated]) and `map` (emoji char => label).
     *
     * @return array{fields: list<string>, groups: array<string, string>, tags: array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>, map: array<string, string>}
     */
    public function tags(): array
    {
        /** @var array{fields: list<string>, groups: array<string, string>, tags: array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>, map: array<string, string>} $shard */
        $shard = $this->shard('tags', ['fields', 'groups', 'tags', 'map']);

        return $shard;
    }

    /** @return array<array-key, string> hexcode => Fluent Emoji folder name */
    public function fluentFolders(): array
    {
        /** @var array<array-key, string> $value */
        $value = $this->imageShard()['fluent'];

        return $value;
    }

    /** @return array<array-key, string> hexcode => the carrier's private-use code point (hex) */
    public function carrierCodes(string $carrier): array
    {
        /** @var array<string, array{codes: array<array-key, string>, reads: array<array-key, string>}> $shard */
        $shard = $this->shard('carriers', ['docomo', 'au', 'softbank', 'google']);

        return $shard[$carrier]['codes'] ?? [];
    }

    /** @return array<array-key, string> the carrier's private-use code point (hex) => hexcode */
    public function carrierReads(string $carrier): array
    {
        /** @var array<string, array{codes: array<array-key, string>, reads: array<array-key, string>}> $shard */
        $shard = $this->shard('carriers', ['docomo', 'au', 'softbank', 'google']);

        return $shard[$carrier]['reads'] ?? [];
    }

    /** @return array<string, list<string>> collection name => hexcodes, in display order */
    public function collections(): array
    {
        $file = $this->directory . '/collections.php';

        if (! is_file($file)) {
            return [];
        }

        /** @var array<string, string> $shard */
        $shard = $this->shards['collections'] ??= $this->load('collections', $file, []);

        return array_map(static fn (string $hexcodes): array => explode(' ', $hexcodes), $shard);
    }

    public function version(): string
    {
        $file = $this->directory . '/VERSION';

        return is_file($file) ? trim((string) file_get_contents($file)) : 'unknown';
    }

    /** @return list<string> the locales shipped, en first */
    public function availableLocales(): array
    {
        $files = glob($this->directory . '/locales/*.php');
        $found = [];

        foreach ($files === false ? [] : $files as $file) {
            $found[] = basename($file, '.php');
        }

        sort($found);

        return array_values(array_unique(['en', ...$found]));
    }

    /**
     * The locale shard, or null when the locale is not shipped (an expected fallback, not a failure).
     * Names are hexcode => name (not stored for en); keywords are hexcode => " | "-joined words.
     *
     * @return array{names: array<array-key, string>, keywords: array<array-key, string>}|null
     *
     * @throws DatasetException when the shard exists but cannot be loaded — the caller classifies that
     *                          as degradable, since English names are a safe fallback
     */
    public function locale(string $locale): ?array
    {
        if (array_key_exists($locale, $this->locales)) {
            return $this->locales[$locale];
        }

        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale) !== 1) {
            return $this->locales[$locale] = null;
        }

        $file = $this->directory . '/locales/' . $locale . '.php';

        if (! is_file($file)) {
            return $this->locales[$locale] = null;
        }

        /** @var array{names: array<array-key, string>, keywords: array<array-key, string>} $shard */
        $shard = $this->load('locale:' . $locale, $file, ['names', 'keywords']);

        return $this->locales[$locale] = $shard;
    }

    /**
     * The catalogue shard, with its record layout checked against the one this code reads.
     *
     * @return array<string, mixed>
     */
    private function catalogueShard(): array
    {
        $shard = $this->shard('emojis', ['fields', 'groups', 'subgroups', 'emojis']);

        if ($shard['fields'] !== Record::FIELDS) {
            throw DatasetException::malformed('emojis', 'the record layout ' . implode(',', Record::FIELDS));
        }

        return $shard;
    }

    /** @return array<string, mixed> */
    private function scannerShard(): array
    {
        return $this->shard('scanner', ['lengths', 'start', 'pictographic', 'components', 'sequences']);
    }

    /** @return array<string, mixed> */
    private function emoticonShard(): array
    {
        return $this->shard('emoticons', ['map', 'risky', 'primary']);
    }

    /** @return array<string, mixed> */
    private function imageShard(): array
    {
        return $this->shard('images', ['bits', 'versions', 'fluent']);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    private function shard(string $name, array $keys): array
    {
        return $this->shards[$name] ??= $this->load($name, $this->directory . '/' . $name . '.php', $keys);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    private function load(string $name, string $file, array $keys): array
    {
        if (! is_file($file)) {
            throw DatasetException::unreadable($name, $file);
        }

        try {
            $data = require $file;
        } catch (Throwable $e) {
            throw DatasetException::unreadable($name, $file, $e);
        }

        if (! is_array($data)) {
            throw DatasetException::malformed($name, 'an array');
        }

        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                throw DatasetException::malformed($name, "a \"{$key}\" key");
            }
        }

        /** @var array<string, mixed> $data $value */
        $value = $data;

        return $value;
    }
}
