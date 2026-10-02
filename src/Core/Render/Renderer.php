<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Render;

use Stringable;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Fit;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Text\Token;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Enums\ShortcodePreset;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmoji;
use Simtabi\Laranail\Emojis\Core\Exceptions\UnsupportedConversion;

/**
 * Turns one emoji into one target form, applying the degradation chain when the target cannot represent it.
 *
 * Every chain ends in a form that always exists (Unicode or Shortcode), so a non-strict conversion never
 * fails; strict conversions throw UnsupportedConversion instead of degrading. Ascii, Shortcode, HtmlEntity,
 * Escaped and Codepoint output is seven-bit ASCII for every emoji in the dataset — the Ascii token is the
 * preset shortcode that round-trips, else the generated transliterated slug.
 */
final readonly class Renderer
{
    /** Must match the class names in resources/assets/styles/emojis.scss; tests/Unit/AssetsTest.php checks both. */
    public const string IMAGE_CLASSES = 'laranail-emoji laranail-emoji-image';

    public const string NATIVE_CLASSES = 'laranail-emoji laranail-emoji-native';

    public function __construct(private Emojis $emojis) {}

    public function render(Token $token, string $original, Mode $target, RenderSettings $settings): Piece
    {
        if ($token->custom instanceof CustomEmoji) {
            return $this->custom($token->custom, $target, $settings);
        }

        $emoji = $this->emojis->catalogue()->byHexcode((string) $token->hexcode);

        if (! $emoji instanceof Emoji) {
            return new Piece($original, false);
        }

        return $this->emoji($emoji, $target, $settings, $token->source === Mode::Unicode ? $original : null);
    }

    public function emoji(Emoji $emoji, Mode $target, RenderSettings $settings, ?string $original = null): Piece
    {
        if ($settings->skinTone instanceof SkinTone && $emoji->base()->skinTonePeople() === 1 && ! $emoji->isSkinToneVariant()) {
            $emoji = $emoji->withSkinTone($settings->skinTone);
            $original = null;
        }

        if ($settings->versionCap instanceof EmojiVersion && $emoji->version->isNewerThan($settings->versionCap) && $this->isDisplayTarget($target)) {
            $capped = $this->capVersion($emoji, $target, $settings);

            if ($capped instanceof Piece) {
                return $capped;
            }

            return $this->degrade($emoji, $target, $settings, $original, skipSelf: true);
        }

        return $this->degrade($emoji, $target, $settings, $original, skipSelf: false);
    }

    public function image(Emoji $emoji, string $set, ?string $locale = null, ?Fit $fit = null): Stringable
    {
        $own = $this->emojis->customImage($emoji);

        if ($own instanceof EmojiImage) {
            return $this->emojis->htmlFactory()->make($this->imgTag($emoji->char, $emoji->name($locale), $own->src, $emoji->hexcode));
        }

        $url = $this->emojis->images()->get($set)->url($emoji);

        return $this->emojis->htmlFactory()->make($url === null
            ? htmlspecialchars($emoji->char, ENT_QUOTES | ENT_HTML5)
            : $this->imageMarkup($emoji, $emoji->name($locale), $url, $set, $fit ?? $this->emojis->options()->imageFit));
    }

    private function isDisplayTarget(Mode $target): bool
    {
        return match ($target) {
            Mode::Emoji, Mode::Unicode, Mode::Text, Mode::Image, Mode::Auto => true,
            default                                                         => false,
        };
    }

    private function degrade(Emoji $emoji, Mode $target, RenderSettings $settings, ?string $original, bool $skipSelf): Piece
    {
        // skipSelf is set only on the version-cap path, so the cap is the reason the target was skipped.
        if ($skipSelf && $settings->strict) {
            throw $settings->versionCap instanceof EmojiVersion
                ? UnsupportedConversion::versionCapped($emoji->hexcode, $target, $settings->versionCap)
                : UnsupportedConversion::for($emoji->hexcode, $target);
        }

        $chain = $skipSelf ? [] : [$target];

        foreach ($settings->chainFor($target, $this->emojis->options()->degradationFor($target)) as $fallback) {
            $chain[] = $fallback;
        }

        foreach ($chain as $index => $mode) {
            if ($settings->strict && $index > 0) {
                throw UnsupportedConversion::for($emoji->hexcode, $target);
            }

            $piece = $this->attempt($emoji, $mode, $settings, $original);

            if ($piece instanceof Piece) {
                return $piece;
            }
        }

        if ($settings->strict) {
            throw UnsupportedConversion::for($emoji->hexcode, $target);
        }

        return new Piece($this->ascii($emoji), false);
    }

    private function attempt(Emoji $emoji, Mode $mode, RenderSettings $settings, ?string $original): ?Piece
    {
        $text = static fn (?string $value): ?Piece => $value === null ? null : new Piece($value, false);

        return match ($mode) {
            Mode::Emoji      => $text($emoji->char),
            Mode::Unicode    => $text($original ?? $emoji->char),
            Mode::Text       => $text($this->textPresentation($emoji)),
            Mode::Ascii      => $text($this->ascii($emoji)),
            Mode::Shortcode  => $text($this->shortcode($emoji, $settings->preset)),
            Mode::Emoticon   => $text($emoji->emoticon()),
            Mode::HtmlEntity => $text(implode('', array_map(static fn (int $cp): string => sprintf('&#x%X;', $cp), $emoji->codepoints))),
            Mode::Escaped    => $text(implode('', array_map($settings->escapeFormat->encode(...), $emoji->codepoints))),
            Mode::Codepoint  => $text($this->codepoints($emoji)),
            Mode::Name       => $text(strtr($this->emojis->options()->nameTemplate, ['{name}' => $emoji->name($settings->locale)])),
            Mode::Tag        => $text($this->emojis->tags()->for($emoji)?->text($this->emojis->options()->tagTemplate)),
            Mode::Image      => $this->imagePiece($emoji, $settings),
            Mode::Carrier    => $text($emoji->carrierCode($settings->carrier)),
            Mode::Auto       => $this->attempt($emoji, $this->emojis->resolveAuto(), $settings, $original),
        };
    }

    private function imagePiece(Emoji $emoji, RenderSettings $settings): ?Piece
    {
        // A replacement registered with useImage() wins over every set; it is already validated, and is
        // shown as-is (a caller-supplied image has no measured crop).
        $own = $this->emojis->customImage($emoji);

        if ($own instanceof EmojiImage) {
            return new Piece($this->imgTag($emoji->char, $emoji->name($settings->locale), $own->src, $emoji->hexcode), true);
        }

        $set = $settings->imageSet ?? $this->emojis->options()->imageSet;
        $url = $this->emojis->images()->get($set)->url($emoji);

        return $url === null ? null : new Piece($this->imageMarkup($emoji, $emoji->name($settings->locale), $url, $set, $settings->fit ?? $this->emojis->options()->imageFit), true);
    }

    /**
     * A plain <img> for Fit::None (or when the crop would remove nothing), otherwise an <svg> whose viewBox is
     * the crop, wrapping the image in a 0–1000 coordinate space. The viewBox does the clipping, so there is no
     * inline style for a Content-Security-Policy to block, and it works for SVG and bitmap sources alike.
     */
    private function imageMarkup(Emoji $emoji, string $label, string $url, string $set, Fit $fit): string
    {
        $box = $fit === Fit::None ? null : $this->cropBox($emoji, $set, $fit);

        if ($box === null) {
            return $this->imgTag($emoji->char, $label, $url, $emoji->hexcode);
        }

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

        return sprintf(
            '<svg class="%s" viewBox="%d %d %d %d" width="1em" height="1em" role="img" aria-label="%s" data-laranail-emoji="%s" data-laranail-fit="%s"><title>%s</title><image href="%s" width="1000" height="1000" preserveAspectRatio="none"/></svg>',
            $e($this->imageClasses()),
            $box[0],
            $box[1],
            $box[2],
            $box[2],
            $e($label),
            $e($emoji->hexcode),
            $fit->value,
            $e($label),
            $e($url),
        );
    }

    /** @return array{0: int, 1: int, 2: int}|null x, y, size in permille; null when nothing would be cropped */
    private function cropBox(Emoji $emoji, string $set, Fit $fit): ?array
    {
        $crop = $this->emojis->images()->crop($emoji, $set);

        if ($crop === null) {
            return null;
        }

        [$inset, $x, $y, $size] = $crop;
        $box = $fit === Fit::Tight ? [$x, $y, $size] : [$inset, $inset, 1000 - 2 * $inset];

        return $box[2] >= 1000 ? null : $box;
    }

    /**
     * An emoji newer than the cap. A skin-tone variant falls back to its base when the base is in range; a
     * ZWJ sequence decomposes into its in-range parts, which is how a platform without the sequence shows
     * it (👨‍🦯‍➡️ → 👨🦯➡️). Otherwise null, and the caller degrades.
     */
    private function capVersion(Emoji $emoji, Mode $target, RenderSettings $settings): ?Piece
    {
        $cap = $settings->versionCap;
        $base = $emoji->base();

        if ($emoji->isSkinToneVariant() && $cap instanceof EmojiVersion && $base->version->isAtMost($cap)) {
            return $this->emoji($base, $target, $settings->withoutSkinTone());
        }

        if (! in_array(0x200D, $emoji->codepoints, true)) {
            return null;
        }

        $parts = [];
        $current = [];

        foreach ([...$emoji->codepoints, 0x200D] as $cp) {
            if ($cp !== 0x200D) {
                $current[] = $cp;

                continue;
            }

            $char = implode('', array_map(static fn (int $c): string => mb_chr($c, 'UTF-8'), $current));
            $part = $this->emojis->catalogue()->byChar($char);

            if (! $part instanceof Emoji || ($cap instanceof EmojiVersion && $part->version->isNewerThan($cap))) {
                return null;
            }

            $parts[] = $part;
            $current = [];
        }

        $pieces = array_map(fn (Emoji $part): Piece => $this->emoji($part, $target, $settings->withoutSkinTone()), $parts);
        $html = array_filter($pieces, static fn (Piece $p): bool => $p->html) !== [];

        return new Piece(implode('', array_map(static fn (Piece $p): string => $html && ! $p->html ? htmlspecialchars($p->text, ENT_QUOTES | ENT_HTML5) : $p->text, $pieces)), $html);
    }

    private function custom(CustomEmoji $custom, Mode $target, RenderSettings $settings): Piece
    {
        $options = $this->emojis->options();
        $code = $options->shortcodeOpen . $custom->name . $options->shortcodeClose;
        $fallback = $custom->fallback ?? $code;

        return match ($target) {
            // The alt text uses the configured delimiters; the data key is always ":name:", a stored format
            // that reads back whatever the delimiters are later changed to.
            Mode::Image                  => new Piece($this->imgTag($code, $custom->label(), $custom->image->src, ':' . $custom->name . ':'), true),
            Mode::Shortcode, Mode::Ascii => new Piece($code, false),
            Mode::Name                   => new Piece(strtr($options->nameTemplate, ['{name}' => $custom->label()]), false),
            Mode::Auto                   => $this->custom($custom, $this->emojis->resolveAuto(), $settings),
            // No form in the target: the caller's fallback, or the code itself, which strict() refuses.
            default => $custom->fallback === null && $settings->strict
                ? throw UnsupportedConversion::for(':' . $custom->name . ':', $target)
                : new Piece($fallback, false),
        };
    }

    private function textPresentation(Emoji $emoji): ?string
    {
        if (! $emoji->hasTextPresentation) {
            return null;
        }

        foreach ($emoji->codepoints as $cp) {
            if ($cp !== 0xFE0F) {
                return mb_chr($cp, 'UTF-8') . "\u{FE0E}";
            }
        }

        return null;
    }

    /**
     * The first of the emoji's codes that still reads back as this emoji: the preset's, then every preset's,
     * then its ASCII code or slug. A code remapped with addShortcode() now reads as another emoji, so it is
     * never written for this one. If no code is left, the character itself: it reads back correctly, where a
     * code that names another emoji would not.
     */
    private function shortcode(Emoji $emoji, ShortcodePreset $preset): string
    {
        $catalogue = $this->emojis->catalogue();

        foreach ([...$emoji->shortcodes($preset), ...$catalogue->allShortcodesOf($emoji)] as $code) {
            if ($catalogue->shortcodeTarget($code) === $emoji->hexcode) {
                return $this->delimited($code);
            }
        }

        $code = $this->asciiCode($emoji);

        return $code === null ? $emoji->char : $this->delimited($code);
    }

    /**
     * Seven-bit, always: the ASCII code (or slug) while it still reads back as this emoji, else the code
     * points (`U+1F680`), which name it unambiguously when no code is left.
     */
    private function ascii(Emoji $emoji): string
    {
        $code = $this->asciiCode($emoji);

        return $code === null ? $this->codepoints($emoji) : $this->delimited($code);
    }

    /** The ASCII code, or the slug, whichever no remap has taken from this emoji; null when both are taken. */
    private function asciiCode(Emoji $emoji): ?string
    {
        $catalogue = $this->emojis->catalogue();

        foreach ([$emoji->asciiCode, $emoji->slug] as $candidate) {
            if (in_array($catalogue->shortcodeTarget($candidate), [null, $emoji->hexcode], true)) {
                return $candidate;
            }
        }

        return null;
    }

    /** `U+1F680`, space-separated for a sequence. */
    private function codepoints(Emoji $emoji): string
    {
        return implode(' ', array_map(static fn (int $cp): string => sprintf('U+%04X', $cp), $emoji->codepoints));
    }

    private function delimited(string $code): string
    {
        $options = $this->emojis->options();

        return $options->shortcodeOpen . $code . $options->shortcodeClose;
    }

    /**
     * The classes on every rendered image: the package's own, which the stylesheet targets, then any extra
     * from `images.class`. Extra classes add hooks; they never replace the ones the layout depends on.
     */
    private function imageClasses(): string
    {
        return trim(self::IMAGE_CLASSES . ' ' . $this->emojis->options()->imageClass);
    }

    private function imgTag(string $alt, string $label, string $url, string $key): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

        return sprintf(
            '<img class="%s" draggable="false" loading="lazy" decoding="async" alt="%s" aria-label="%s" title="%s" src="%s" data-laranail-emoji="%s">',
            $e($this->imageClasses()),
            $e($alt),
            $e($label),
            $e($label),
            $e($url),
            $e($key),
        );
    }
}
