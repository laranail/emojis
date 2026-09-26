<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Render;

use Stringable;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Text\Token;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
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

    public function image(Emoji $emoji, string $set, ?string $locale = null): Stringable
    {
        $url = $this->emojis->images()->get($set)->url($emoji);

        return $this->emojis->htmlFactory()->make($url === null ? htmlspecialchars($emoji->char, ENT_QUOTES | ENT_HTML5) : $this->imgTag($emoji->char, $emoji->name($locale), $url, $emoji->hexcode));
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
        if ($skipSelf && $settings->strict) {
            throw UnsupportedConversion::for($emoji->hexcode, $target);
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
            Mode::Codepoint  => $text(implode(' ', array_map(static fn (int $cp): string => sprintf('U+%04X', $cp), $emoji->codepoints))),
            Mode::Name       => $text(strtr($this->emojis->options()->nameTemplate, ['{name}' => $emoji->name($settings->locale)])),
            Mode::Image      => $this->imagePiece($emoji, $settings),
            Mode::Auto       => $this->attempt($emoji, $this->emojis->resolveAuto(), $settings, $original),
        };
    }

    private function imagePiece(Emoji $emoji, RenderSettings $settings): ?Piece
    {
        $url = $this->emojis->images()->get($settings->imageSet ?? $this->emojis->options()->imageSet)->url($emoji);

        return $url === null ? null : new Piece($this->imgTag($emoji->char, $emoji->name($settings->locale), $url, $emoji->hexcode), true);
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
            Mode::Image                  => new Piece($this->imgTag($code, $custom->label(), $custom->imageUrl, ':' . $custom->name . ':'), true),
            Mode::Shortcode, Mode::Ascii => new Piece($code, false),
            Mode::Name                   => new Piece(strtr($options->nameTemplate, ['{name}' => $custom->label()]), false),
            Mode::Auto                   => $this->custom($custom, $this->emojis->resolveAuto(), $settings),
            default                      => new Piece($fallback, false),
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

    private function shortcode(Emoji $emoji, ShortcodePreset $preset): string
    {
        $index = $this->emojis->catalogue()->shortcodeIndex();
        $options = $this->emojis->options();

        foreach ($emoji->shortcodes($preset) as $code) {
            if (($index[$code] ?? null) === $emoji->hexcode) {
                return $options->shortcodeOpen . $code . $options->shortcodeClose;
            }
        }

        return $this->ascii($emoji);
    }

    private function ascii(Emoji $emoji): string
    {
        $options = $this->emojis->options();

        return $options->shortcodeOpen . $emoji->asciiCode . $options->shortcodeClose;
    }

    private function imgTag(string $alt, string $label, string $url, string $key): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

        return sprintf(
            '<img class="%s" draggable="false" loading="lazy" decoding="async" alt="%s" aria-label="%s" title="%s" src="%s" data-laranail-emoji="%s">',
            $e($this->emojis->options()->imageClass),
            $e($alt),
            $e($label),
            $e($label),
            $e($url),
            $e($key),
        );
    }
}
