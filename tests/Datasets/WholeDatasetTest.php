<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiId;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Enums\EscapeFormat;
use Simtabi\Laranail\Emojis\Core\Enums\Qualification;
use Simtabi\Laranail\Emojis\Core\Enums\ShortcodePreset;

/*
 * Sweeps over the whole dataset. Each asserts how many items it inspected against a floor, because a sweep
 * whose loop stops matching reports a clean dataset rather than a broken search. The exact counts are pinned
 * by tools/build-dataset.php against emoji-test.txt's own status footer; the floors here only catch a
 * catalogue that has quietly shrunk or a loop that has quietly emptied.
 */
const FLOOR_EMOJI = 3900;
const FLOOR_SEQUENCES = 5200;

/** @return list<Emoji> */
function everyEmoji(): array
{
    static $all = null;

    return $all ??= emojis()->all()->all();
}

it('holds every fully-qualified emoji and component', function (): void {
    expect(count(everyEmoji()))->toBeGreaterThanOrEqual(FLOOR_EMOJI);
});

it('scans every known sequence, in every qualification, back to exactly one emoji', function (): void {
    $data = require dirname(__DIR__, 2) . '/resources/data/scanner.php';
    $checked = 0;

    foreach ($data['sequences'] as $sequence => [$hex, $quality]) {
        $matches = emojis()->text((string) $sequence)->includeTextPresentation()->extract();

        expect($matches)->toHaveCount(1, "sequence for {$hex}")
            ->and($matches[0]->emoji?->hexcode)->toBe((string) $hex)
            ->and($matches[0]->length)->toBe(strlen((string) $sequence));
        $checked++;
    }

    expect($checked)->toBeGreaterThanOrEqual(FLOOR_SEQUENCES);
});

it('scans each emoji alone between words and counts it as one character of width two', function (): void {
    $checked = 0;

    foreach (everyEmoji() as $emoji) {
        $text = emojis()->text("a{$emoji->char}b");

        expect($text->count())->toBe(1, $emoji->hexcode)
            ->and($text->length())->toBe(3, $emoji->hexcode)
            ->and($text->width())->toBe(4, $emoji->hexcode);
        $checked++;
    }

    expect($checked)->toBeGreaterThanOrEqual(FLOOR_EMOJI);
});

it('writes seven-bit ASCII for every emoji and reads it back', function (): void {
    $checked = 0;

    foreach (everyEmoji() as $emoji) {
        $ascii = emojis()->text($emoji->char)->toAscii();

        expect($ascii)->toMatch('/^[\x21-\x7E]+$/', $emoji->hexcode)
            ->and(emojis()->text($ascii)->from(Mode::Shortcode)->toEmoji())->toBe($emoji->char, $emoji->hexcode . ' via ' . $ascii);
        $checked++;
    }

    expect($checked)->toBeGreaterThanOrEqual(FLOOR_EMOJI);
});

it('round-trips every emoji through every shortcode preset', function (ShortcodePreset $preset): void {
    $checked = 0;

    foreach (everyEmoji() as $emoji) {
        $code = emojis()->text($emoji->char)->preset($preset)->toShortcodes();

        expect(emojis()->text($code)->from(Mode::Shortcode)->toEmoji())->toBe($emoji->char, "{$preset->value} {$emoji->hexcode} {$code}");
        $checked++;
    }

    expect($checked)->toBeGreaterThanOrEqual(FLOOR_EMOJI);
})->with(ShortcodePreset::cases());

it('round-trips every emoji through entities, escapes and code points', function (Mode $mode, ?EscapeFormat $format): void {
    $checked = 0;

    foreach (everyEmoji() as $emoji) {
        $encoded = emojis()->text($emoji->char)->to($mode, $format);

        expect($encoded)->toMatch('/^[\x20-\x7E]+$/', $emoji->hexcode)
            ->and(emojis()->text($encoded)->from($mode)->toEmoji())->toBe($emoji->char, $emoji->hexcode);
        $checked++;
    }

    expect($checked)->toBeGreaterThanOrEqual(FLOOR_EMOJI);
})->with([
    'entities'    => [Mode::HtmlEntity, null],
    'php'         => [Mode::Escaped, EscapeFormat::Php],
    'javascript'  => [Mode::Escaped, EscapeFormat::JavaScript],
    'python'      => [Mode::Escaped, EscapeFormat::Python],
    'code points' => [Mode::Codepoint, null],
]);

it('gives text presentation exactly where Unicode defines it', function (): void {
    $withText = 0;

    foreach (everyEmoji() as $emoji) {
        $text = emojis()->text($emoji->char)->to(Mode::Text);

        if ($emoji->hasTextPresentation) {
            expect($text)->toEndWith("\u{FE0E}", $emoji->hexcode);
            $withText++;
        } else {
            expect($text)->not->toContain("\u{FE0E}", $emoji->hexcode);
        }
    }

    // emoji-variation-sequences.txt defines 371 text sequences; keycap bases are not single-emoji records.
    expect($withText)->toBeGreaterThan(300);
});

it('links every skin-tone variant to a base that lists it', function (): void {
    $checked = 0;

    foreach (everyEmoji() as $emoji) {
        if (! $emoji->isSkinToneVariant()) {
            continue;
        }

        $base = $emoji->base();

        expect($base->isSkinToneVariant())->toBeFalse($emoji->hexcode)
            ->and(in_array($emoji->hexcode, $base->skins, true))->toBeTrue($emoji->hexcode)
            ->and($base->withSkinTone(...$emoji->tones)->hexcode)->toBe($emoji->hexcode);
        $checked++;
    }

    expect($checked)->toBeGreaterThan(2000);
});

it('names every emoji in every shipped locale', function (): void {
    $checked = 0;

    foreach (emojis()->availableLocales() as $locale) {
        $localized = 0;
        $bases = 0;

        foreach (everyEmoji() as $emoji) {
            if ($emoji->isSkinToneVariant()) {
                continue;
            }

            $name = $emoji->name($locale);
            expect($name)->toBeString()->not->toBe('');
            $bases++;
            $localized += $locale === 'en' || $name !== $emoji->englishName ? 1 : 0;
            $checked++;
        }

        // CLDR has no annotation for emoji newer than the pinned CLDR release, which fall back to English.
        // Measured minimum across the shipped locales with derived annotations merged: 93.7% (2026-09-26).
        expect($localized / $bases)->toBeGreaterThan(0.9, $locale);
    }

    expect($checked)->toBeGreaterThan(FLOOR_EMOJI * 10);
});

it('resolves every EmojiId case', function (): void {
    $checked = 0;

    foreach (EmojiId::cases() as $case) {
        expect(emojis()->get($case)->hexcode)->toBe($case->value);
        $checked++;
    }

    expect($checked)->toBeGreaterThan(1800);
});

it('gives an image URL exactly where the set publishes one', function (): void {
    $withTwemoji = 0;

    foreach (everyEmoji() as $emoji) {
        $url = $emoji->imageUrl('twemoji');

        if ($url !== null) {
            expect($url)->toStartWith('https://cdn.jsdelivr.net/gh/jdecked/twemoji@');
            $withTwemoji++;
        }
    }

    // Twemoji 17.0.3 covers everything up to Emoji 17; Emoji 18 additions render natively.
    expect($withTwemoji)->toBeGreaterThan(3800);
});

it('marks only single-character text-default emoji as gated', function (): void {
    $data = require dirname(__DIR__, 2) . '/resources/data/scanner.php';
    $gated = 0;

    foreach ($data['sequences'] as $sequence => [, $quality]) {
        if ($quality === Qualification::TextDefault->value) {
            expect(mb_strlen((string) $sequence, 'UTF-8'))->toBe(1);
            $gated++;
        }
    }

    expect($gated)->toBeGreaterThan(100);
});

it('keeps the tone key order of two-person emoji', function (): void {
    expect((string) emojis()->get('handshake')->withSkinTone(SkinTone::Dark, SkinTone::Light))->toBe('🫱🏿‍🫲🏻');
});

it('keeps the shipped dataset inside its size budget', function (): void {
    // Measured 2026-09-26: 13,200 KiB (du -sk) across 32 files, 8.3 MiB of it the 24 locale shards. The budget leaves
    // room for a Unicode version or two; a jump past it means a shard changed shape, not that emoji grew.
    $bytes = 0;
    $files = 0;

    foreach ([...glob(dirname(__DIR__, 2) . '/resources/data/*') ?: [], ...glob(dirname(__DIR__, 2) . '/resources/data/locales/*') ?: []] as $file) {
        if (is_file($file)) {
            $bytes += (int) filesize($file);
            $files++;
        }
    }

    expect($files)->toBeGreaterThanOrEqual(30)
        ->and($bytes)->toBeLessThan(16 * 1024 * 1024);
});
