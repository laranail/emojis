<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Catalogue;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Data\Record;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\Carrier;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiId;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Enums\Subgroup;
use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Enums\SequenceType;
use Simtabi\Laranail\Emojis\Core\Enums\ShortcodePreset;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmojiRegistry;

/**
 * O(1) lookups over the dataset, and the one place records become Emoji objects.
 *
 * Objects are hydrated on first use and memoised, so `$catalogue->byHexcode('1F600')` returns the same
 * instance every time and iterating the whole catalogue builds each object once. Internal: reach it
 * through Emojis.
 *
 * @phpstan-import-type EmojiRecord from DatasetStore
 */
final class Catalogue
{
    /** @var array<string, Emoji> */
    private array $hydrated = [];

    /** @var array<string, string>|null slug => hexcode */
    private ?array $slugs = null;

    /** @var array<string, list<string>>|null hexcode => emoticons */
    private ?array $emoticonsByHex = null;

    /** @var array<int, array<string, string>> risky flag (0/1) => emoticon => hexcode */
    private array $activeEmoticons = [];

    public function __construct(
        private readonly Emojis $emojis,
        private readonly DatasetStore $data,
        private readonly CustomEmojiRegistry $custom,
    ) {}

    public function byHexcode(string $hexcode): ?Emoji
    {
        $hex = strtoupper(trim(str_replace([' ', '_', 'U+', 'u+'], ['-', '-', '', ''], $hexcode), '-'));

        if (isset($this->hydrated[$hex])) {
            return $this->hydrated[$hex];
        }

        $record = $this->data->record($hex);

        if ($record === null) {
            // Accept a hexcode written without FE0F ("2764" for ❤️) or minimally qualified.
            $sequence = $this->data->sequences()[$this->charsOf($hex)] ?? null;

            return $sequence === null ? null : $this->byHexcode($sequence[0]);
        }

        return $this->hydrated[$hex] = $this->hydrate($hex, $record);
    }

    /** Exact lookup of an emoji character in any qualification ("☺", "☺️", "👋🏽"). */
    public function byChar(string $char): ?Emoji
    {
        $sequence = $this->data->sequences()[$char] ?? null;

        return $sequence === null ? null : $this->byHexcode($sequence[0]);
    }

    /** ":thumbsup:", "thumbsup", "+1" in any preset, a curated alias, a registered extra, or a slug. */
    public function byShortcode(string $code): ?Emoji
    {
        $hex = $this->shortcodeTarget($code);

        return $hex === null ? null : $this->byHexcode($hex);
    }

    /**
     * The hexcode a shortcode means now: one added with addShortcode() wins over the dataset's. Reading and
     * writing both ask this, so a remapped code is written only for the emoji it now reads back as.
     */
    public function shortcodeTarget(string $code): ?string
    {
        $code = strtolower(trim($code, ': '));

        return $this->custom->shortcodes()[$code] ?? $this->shortcodeIndex()[$code] ?? null;
    }

    public function bySlug(string $slug): ?Emoji
    {
        $this->slugs ??= $this->buildSlugs();
        $hex = $this->slugs[strtolower($slug)] ?? null;

        return $hex === null ? null : $this->byHexcode($hex);
    }

    public function byEmoticon(string $emoticon): ?Emoji
    {
        $hex = $this->activeEmoticons(risky: true)[$emoticon] ?? null;

        return $hex === null ? null : $this->byHexcode($hex);
    }

    /**
     * The emoticons in force, emoticon => hexcode: the dataset's, then the caller's added ones (which win),
     * less any switched off. The one definition the scanner, lookups and listings all read.
     *
     * Risky dataset entries are left out unless $risky. An emoticon the caller added is never treated as
     * risky, even when the dataset marks the same text so: adding it was the decision to match it.
     *
     * @return array<string, string>
     */
    public function activeEmoticons(bool $risky = false): array
    {
        if (isset($this->activeEmoticons[$risky])) {
            return $this->activeEmoticons[$risky];
        }

        $custom = $this->custom->emoticons();
        $disabled = $this->custom->disabledEmoticons();
        $excluded = $risky ? [] : array_diff_key(array_flip($this->data->riskyEmoticons()), $custom);
        $active = [];

        foreach ([...$this->data->emoticonMap(), ...$custom] as $emoticon => $hex) {
            $emoticon = (string) $emoticon;

            if (! isset($disabled[$emoticon]) && ! isset($excluded[$emoticon])) {
                $active[$emoticon] = $hex;
            }
        }

        return $this->activeEmoticons[$risky] = $active;
    }

    /** @internal called when an emoticon is added or switched off, so memoised views are rebuilt */
    public function forgetEmoticons(): void
    {
        $this->activeEmoticons = [];
        $this->emoticonsByHex = null;
    }

    /**
     * The forgiving lookup behind Emojis::find(): an EmojiId, an Emoji, a character, a hexcode, a
     * shortcode (with or without colons), a slug, an emoticon or an English CLDR name ("man: red hair") —
     * tried in that order.
     */
    public function find(Emoji|EmojiId|string $key): ?Emoji
    {
        if ($key instanceof Emoji) {
            return $key;
        }

        if ($key instanceof EmojiId) {
            return $this->byHexcode($key->value);
        }

        $key = trim($key);

        if ($key === '') {
            return null;
        }

        return $this->byChar($key)
            ?? (preg_match('/^(?:U\+)?[0-9A-Fa-f]{2,6}(?:[ _-](?:U\+)?[0-9A-Fa-f]{2,6})*$/', $key) === 1 ? $this->byHexcode($key) : null)
            ?? $this->byShortcode($key)
            ?? $this->bySlug($key)
            ?? $this->byEmoticon($key)
            ?? $this->bySlug(trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($key)), '_'));
    }

    /** @return list<string> the hexcodes of a curated collection; unknown names give an empty list */
    public function collection(string $name): array
    {
        return $this->data->collections()[$name] ?? [];
    }

    /** @return list<Emoji> every fully-qualified emoji and component, in CLDR order */
    public function all(): array
    {
        $all = [];

        foreach (array_keys($this->data->emojis()) as $hex) {
            $emoji = $this->byHexcode((string) $hex);

            if ($emoji instanceof Emoji) {
                $all[] = $emoji;
            }
        }

        return $all;
    }

    /** @return list<string> */
    public function shortcodesOf(Emoji $emoji, ?ShortcodePreset $preset = null): array
    {
        $preset ??= $this->emojis->options()->preset;
        $codes = $this->data->shortcodePreset($preset->value)[$emoji->hexcode] ?? '';

        return $codes === '' ? [] : explode(' ', $codes);
    }

    /** @return list<string> */
    public function allShortcodesOf(Emoji $emoji): array
    {
        $codes = [];

        foreach (ShortcodePreset::cases() as $preset) {
            array_push($codes, ...$this->shortcodesOf($emoji, $preset));
        }

        return array_values(array_unique($codes));
    }

    public function emoticonOf(Emoji $emoji): ?string
    {
        $primary = $this->data->primaryEmoticons()[$emoji->hexcode] ?? null;

        if ($primary !== null && ($this->activeEmoticons(risky: true)[$primary] ?? null) === $emoji->hexcode) {
            return $primary;
        }

        // The primary was switched off or remapped to another emoji, and writing it would read back as
        // something else: use another emoticon that still means this emoji, or none (Emoticon mode degrades).
        if ($primary === null) {
            return null;
        }

        $fallback = array_search($emoji->hexcode, $this->activeEmoticons(), true);

        return $fallback === false ? null : $fallback;
    }

    /** @return list<string> every emoticon for this emoji, opt-in-only ones included */
    public function emoticonsOf(Emoji $emoji): array
    {
        if ($this->emoticonsByHex === null) {
            $this->emoticonsByHex = [];

            foreach ($this->activeEmoticons(risky: true) as $emoticon => $hex) {
                $this->emoticonsByHex[$hex][] = $emoticon;
            }
        }

        return $this->emoticonsByHex[$emoji->hexcode] ?? [];
    }

    /** The carrier code point (hex) for an emoji; a skin-tone variant falls back to its base, as carriers had none. */
    public function carrierCodeOf(Emoji $emoji, Carrier $carrier): ?string
    {
        $codes = $this->data->carrierCodes($carrier->value);

        return $codes[$emoji->hexcode] ?? ($emoji->baseHexcode === null ? null : $codes[$emoji->baseHexcode] ?? null);
    }

    /** @return array<array-key, string> code => hexcode */
    public function shortcodeIndex(): array
    {
        return $this->data->shortcodeIndex();
    }

    /** @param EmojiRecord $record */
    private function hydrate(string $hex, array $record): Emoji
    {
        $codepoints = array_map(static fn (string $part): int => (int) hexdec($part), explode('-', $hex));

        return new Emoji(
            emojis: $this->emojis,
            hexcode: $hex,
            char: $record[Record::EMOJI],
            codepoints: $codepoints,
            englishName: $record[Record::NAME],
            slug: $record[Record::SLUG],
            asciiCode: $record[Record::ASCII],
            group: Group::fromIndex($record[Record::GROUP]),
            subgroup: Subgroup::fromIndex($record[Record::SUBGROUP]),
            version: EmojiVersion::from($record[Record::VERSION]),
            type: SequenceType::from($record[Record::TYPE]),
            isComponent: $record[Record::COMPONENT],
            baseHexcode: $record[Record::BASE],
            tones: $record[Record::TONES] === null ? [] : array_map(static fn (string $t): SkinTone => SkinTone::from((int) $t), explode('-', $record[Record::TONES])),
            skins: Record::skins($record[Record::SKINS]),
            hasTextPresentation: $record[Record::TEXT],
            region: $record[Record::REGION],
            gender: $record[Record::GENDER],
            hair: $record[Record::HAIR],
            direction: $record[Record::DIRECTION],
            imageCoverage: $record[Record::IMAGES],
        );
    }

    /** @return array<string, string> */
    private function buildSlugs(): array
    {
        $slugs = [];

        foreach ($this->data->emojis() as $hex => $record) {
            $slugs[$record[Record::SLUG]] = (string) $hex;
        }

        return $slugs;
    }

    private function charsOf(string $hex): string
    {
        $chars = '';

        foreach (explode('-', $hex) as $part) {
            if (preg_match('/^[0-9A-F]{1,6}$/', $part) !== 1) {
                return '';
            }

            $chars .= mb_chr((int) hexdec($part), 'UTF-8');
        }

        return $chars;
    }
}
