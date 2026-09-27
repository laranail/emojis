<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core;

use Stringable;
use JsonSerializable;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\Carrier;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Enums\Subgroup;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Enums\EscapeFormat;
use Simtabi\Laranail\Emojis\Core\Enums\SequenceType;
use Simtabi\Laranail\Emojis\Core\Enums\ShortcodePreset;
use Simtabi\Laranail\Emojis\Core\Exceptions\EmojiNotFound;
use Simtabi\Laranail\Emojis\Core\Catalogue\EmojiCollection;

/**
 * One fully-qualified emoji (or component) from the catalogue.
 *
 * Immutable. Data accessors are properties; anything that needs the rest of the catalogue — localized
 * names, shortcodes, skin-tone variants, rendering — goes through the Emojis instance that produced it,
 * so it honours that instance's configuration and custom registrations.
 *
 *     $wave = $emojis->get('wave');
 *     $wave->withSkinTone(SkinTone::Medium)->render(Mode::Shortcode);   // ":wave_tone3:"
 *     $wave->name('fr');                                                // "main qui fait coucou"
 */
final readonly class Emoji implements JsonSerializable, Stringable
{
    /**
     * @param list<int> $codepoints
     * @param list<SkinTone> $tones the tones this variant carries, in sequence order
     * @param array<array-key, string> $skins tone key ("3", "1-5") => hexcode, on a base emoji
     */
    public function __construct(
        private Emojis $emojis,
        public string $hexcode,
        public string $char,
        public array $codepoints,
        public string $englishName,
        public string $slug,
        public string $asciiCode,
        public Group $group,
        public Subgroup $subgroup,
        public EmojiVersion $version,
        public SequenceType $type,
        public bool $isComponent,
        public ?string $baseHexcode,
        public array $tones,
        public array $skins,
        public bool $hasTextPresentation,
        public ?string $region,
        public ?string $gender,
        public ?string $hair,
        public ?string $direction,
        public int $imageCoverage,
    ) {}

    /** The fully-qualified text form: "1F600" or "U+1F600" style input lives in render(Mode::Codepoint). */
    public function __toString(): string
    {
        return $this->char;
    }

    // ---- identity -------------------------------------------------------------------------------

    public function is(self|string $other): bool
    {
        return $other instanceof self ? $other->hexcode === $this->hexcode : $this->emojis->find($other)?->hexcode === $this->hexcode;
    }

    public function isFlag(): bool
    {
        return $this->type === SequenceType::Flag || $this->type === SequenceType::Tag;
    }

    public function isSkinToneVariant(): bool
    {
        return $this->baseHexcode !== null;
    }

    public function supportsSkinTones(): bool
    {
        return $this->skins !== [];
    }

    /** How many people a skin tone applies to: 2 for handshakes and couples, 1 otherwise, 0 if none. */
    public function skinTonePeople(): int
    {
        $people = 0;

        foreach (array_keys($this->skins) as $key) {
            // Keys are tone keys ("3", "1-5"); PHP stores the single-digit ones as integers.
            $people = max($people, substr_count((string) $key, '-') + 1);
        }

        return $people;
    }

    // ---- names and words -----------------------------------------------------------------------

    /** The CLDR name in a locale (the configured one by default), falling back to English. */
    public function name(?string $locale = null): string
    {
        return $this->emojis->locales()->name($this, $locale);
    }

    /** @return list<string> CLDR keywords in a locale, falling back to English */
    public function keywords(?string $locale = null): array
    {
        return $this->emojis->locales()->keywords($this, $locale);
    }

    /** The primary shortcode in a preset (no delimiters), or null when the preset has none. */
    public function shortcode(?ShortcodePreset $preset = null): ?string
    {
        return $this->emojis->catalogue()->shortcodesOf($this, $preset)[0] ?? null;
    }

    /** @return list<string> every shortcode for this emoji in a preset, primary first */
    public function shortcodes(?ShortcodePreset $preset = null): array
    {
        return $this->emojis->catalogue()->shortcodesOf($this, $preset);
    }

    /** @return list<string> shortcodes across every preset, deduplicated */
    public function allShortcodes(): array
    {
        return $this->emojis->catalogue()->allShortcodesOf($this);
    }

    public function emoticon(): ?string
    {
        return $this->emojis->catalogue()->emoticonOf($this);
    }

    /** @return list<string> every ASCII emoticon that parses to this emoji */
    public function emoticons(): array
    {
        return $this->emojis->catalogue()->emoticonsOf($this);
    }

    /** The emoji as a Japanese carrier's private-use character, or null when that carrier had no such emoji. */
    public function carrierCode(Carrier $carrier): ?string
    {
        $code = $this->emojis->catalogue()->carrierCodeOf($this, $carrier);

        return $code === null ? null : mb_chr((int) hexdec($code), 'UTF-8');
    }

    // ---- variants ----------------------------------------------------------------------------

    /**
     * The variant with the given skin tone(s). One tone applies to everyone; two-person emoji
     * (handshake, couples) take one tone per person.
     *
     * @throws EmojiNotFound when this emoji has no such variant
     */
    public function withSkinTone(SkinTone ...$tones): self
    {
        $base = $this->base();

        if ($tones === []) {
            return $base;
        }

        // Unicode encodes "both people the same tone" as a single modifier (🤝🏽), and mixed tones as
        // two (🫱🏻‍🫲🏿), so equal tones collapse to one key before the lookup.
        $values = array_map(static fn (SkinTone $t): int => $t->value, $tones);
        $key = count(array_unique($values)) === 1 ? (string) $values[0] : implode('-', $values);
        $hex = $base->skins[$key] ?? $base->skins[$key . '-' . $key] ?? throw EmojiNotFound::for('skin tone of ' . $base->hexcode, $key);

        return $this->emojis->catalogue()->byHexcode($hex) ?? throw EmojiNotFound::for('hexcode', $hex);
    }

    public function withoutSkinTone(): self
    {
        return $this->base();
    }

    /** The emoji without skin tones (itself when it has none). */
    public function base(): self
    {
        return $this->baseHexcode === null ? $this : ($this->emojis->catalogue()->byHexcode($this->baseHexcode) ?? $this);
    }

    /** Every skin-tone variant of this emoji's base, in tone order. */
    public function skinToneVariants(): EmojiCollection
    {
        $catalogue = $this->emojis->catalogue();

        return new EmojiCollection(array_values(array_filter(array_map(
            $catalogue->byHexcode(...),
            array_values($this->base()->skins),
        ))));
    }

    // ---- rendering ---------------------------------------------------------------------------

    /** Render in any target mode with this instance's defaults. Image mode returns the <img> tag. */
    public function render(Mode $mode = Mode::Emoji, ?EscapeFormat $format = null): string
    {
        return $this->emojis->text($this->char)->to($mode, $format);
    }

    public function toText(): string
    {
        return $this->render(Mode::Text);
    }

    public function toAscii(): string
    {
        return $this->render(Mode::Ascii);
    }

    public function toHtmlEntity(): string
    {
        return $this->render(Mode::HtmlEntity);
    }

    public function toCodepoints(): string
    {
        return $this->render(Mode::Codepoint);
    }

    public function escaped(EscapeFormat $format = EscapeFormat::Php): string
    {
        return $this->render(Mode::Escaped, $format);
    }

    /** The image URL in a set (the configured one by default), or null when the set has no image. */
    public function imageUrl(?string $set = null): ?string
    {
        return $this->emojis->images()->get($set ?? $this->emojis->options()->imageSet)->url($this);
    }

    public function toImage(?string $set = null): Stringable
    {
        return $this->emojis->renderer()->image($this, $set ?? $this->emojis->options()->imageSet, $this->emojis->options()->locale);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'emoji'             => $this->char,
            'hexcode'           => $this->hexcode,
            'name'              => $this->englishName,
            'slug'              => $this->slug,
            'shortcode'         => $this->shortcode(),
            'group'             => $this->group->value,
            'subgroup'          => $this->subgroup->value,
            'version'           => $this->version->value,
            'type'              => $this->type->value,
            'skin_tones'        => array_map(static fn (SkinTone $t): int => $t->value, $this->tones),
            'base'              => $this->baseHexcode,
            'region'            => $this->region,
            'text_presentation' => $this->hasTextPresentation,
        ];
    }
}
