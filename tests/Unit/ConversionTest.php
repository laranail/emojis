<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Enums\EscapeFormat;
use Simtabi\Laranail\Emojis\Core\Enums\ShortcodePreset;
use Simtabi\Laranail\Emojis\Core\Exceptions\UnsupportedConversion;

/*
 * The conversion matrix: every source form × every target, on one emoji. Rows are how 👋🏽 is written in
 * the input; columns are what it becomes. The Datasets suite repeats the round trips over all 3,972 emoji.
 */
dataset('sources', [
    'unicode'        => [Mode::Unicode, 'hi 👋🏽'],
    'shortcode'      => [Mode::Shortcode, 'hi :wave_tone3:'],
    'slack tone'     => [Mode::Shortcode, 'hi :wave::skin-tone-4:'],
    'html entity'    => [Mode::HtmlEntity, 'hi &#x1F44B;&#x1F3FD;'],
    'decimal entity' => [Mode::HtmlEntity, 'hi &#128075;&#127997;'],
    'php escape'     => [Mode::Escaped, 'hi \u{1F44B}\u{1F3FD}'],
    'js escape'      => [Mode::Escaped, "hi \x5CuD83D\x5CuDC4B\x5CuD83C\x5CuDFFD"],
    'python escape'  => [Mode::Escaped, 'hi \U0001F44B\U0001F3FD'],
    'codepoint'      => [Mode::Codepoint, 'hi U+1F44B U+1F3FD'],
    'own image'      => [Mode::Image, 'hi <img class="emoji" alt="👋🏽" data-laranail-emoji="1F44B-1F3FD">'],
]);

dataset('targets', [
    'emoji'                          => [Mode::Emoji, 'hi 👋🏽'],
    'text degrades to unicode'       => [Mode::Text, 'hi 👋🏽'],
    'ascii'                          => [Mode::Ascii, 'hi :wave_tone3:'],
    'emoticon degrades to shortcode' => [Mode::Emoticon, 'hi :wave_tone3:'],
    'shortcode'                      => [Mode::Shortcode, 'hi :wave_tone3:'],
    'html entity'                    => [Mode::HtmlEntity, 'hi &#x1F44B;&#x1F3FD;'],
    'escaped'                        => [Mode::Escaped, 'hi \u{1F44B}\u{1F3FD}'],
    'codepoint'                      => [Mode::Codepoint, 'hi U+1F44B U+1F3FD'],
    'name'                           => [Mode::Name, 'hi [waving hand: medium skin tone]'],
]);

it('converts every source form to every target', function (Mode $source, string $input, Mode $target, string $expected): void {
    $converted = emojis()->text($input)->from($source)->to($target);

    // Slack counts skin tones from 2, so ":skin-tone-4:" is tone 3 (Medium) — the same 👋🏽 as every other row.
    expect($converted)->toBe($expected);
})->with('sources')->with('targets');

it('renders images with escaped surroundings, label and data key', function (): void {
    $html = emojis()->text('<b>hi</b> 👋')->to(Mode::Image);

    expect($html)->toStartWith('&lt;b&gt;hi&lt;/b&gt; <img ')
        ->and($html)->toContain('alt="👋"')
        ->and($html)->toContain('aria-label="waving hand"')
        ->and($html)->toContain('src="https://cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.3/assets/svg/1f44b.svg"')
        ->and($html)->toContain('data-laranail-emoji="1F44B"')
        ->and($html)->toContain('loading="lazy"');
});

it('uses VS15 only where Unicode defines a text presentation', function (): void {
    expect(emojis()->text('☺️ 😀')->to(Mode::Text))->toBe("☺\u{FE0E} 😀");
});

it('throws instead of degrading when strict', function (): void {
    emojis()->text('😀')->strict()->to(Mode::Text);
})->throws(UnsupportedConversion::class);

it('lets a caller override a degradation chain', function (): void {
    expect(emojis()->text('😀')->degrade(Mode::Text, Mode::Name)->to(Mode::Text))->toBe('[grinning face]');
});

it('writes emoticons where they exist', function (): void {
    expect(emojis()->text('🙂 😀 ❤️ 🚀')->to(Mode::Emoticon))->toBe(':) :D <3 :rocket:');
});

it('picks the requested shortcode preset and always emits ASCII', function (): void {
    expect(emojis()->text('👍')->preset(ShortcodePreset::Slack)->to(Mode::Shortcode))->toBe(':+1:')
        ->and(emojis()->text('🪇 🫩')->to(Mode::Ascii))->toMatch('/^[\x20-\x7E]+$/');
});

it('supports every escape format', function (EscapeFormat $format, string $expected): void {
    expect(emojis()->text('😀')->toEscaped($format))->toBe($expected);
})->with([
    [EscapeFormat::Php, '\u{1F600}'],
    [EscapeFormat::JavaScript, "\x5CuD83D\x5CuDE00"],
    [EscapeFormat::Python, '\U0001F600'],
    [EscapeFormat::Css, '\1F600 '],
]);

it('caps the Emoji version like an older platform would', function (): void {
    $capped = emojis()->text('❤️‍🔥 👋🏽 🫩 🚀')->supportedUpTo(EmojiVersion::V13_0)->to(Mode::Emoji);

    // E13.1 ZWJ decomposes into its parts, an in-range tone variant stays, E16 degrades, E0.6 is untouched.
    expect($capped)->toBe('❤️🔥 👋🏽 :face_with_eye_bags: 🚀');
});

it('localizes names and composes skin-tone names', function (): void {
    expect(emojis()->text('🚀 👋🏽')->locale('fr')->to(Mode::Name))->toBe('[fusée] [signe de la main: peau légèrement mate]');
});

it('applies a skin tone to single-person emoji only', function (): void {
    expect(emojis()->text('👋 🤝 🚀')->skinTone(SkinTone::Dark)->toEmoji())->toBe('👋🏿 🤝 🚀');
});

it('runs post-processing stages in order', function (): void {
    $out = emojis()->text('a 😀 b')->through(static fn (string $s): string => "[{$s}]")->through(static fn (string $s): string => "<{$s}>")->toEmoji();

    expect($out)->toBe('a <[😀]> b');
});

it('normalizes to fully-qualified sequences', function (): void {
    expect(emojis()->text("\u{2764} \u{1F3F3}\u{200D}\u{1F308}")->includeTextPresentation()->normalize())->toBe("\u{2764}\u{FE0F} \u{1F3F3}\u{FE0F}\u{200D}\u{1F308}");
});

it('replaces through a callback', function (): void {
    expect(emojis()->text('go 🚀 :wave:')->replace(static fn ($e, string $original): string => '<' . $e->slug . '>'))->toBe('go <rocket> <waving_hand>');
});

it('offers the convert() shortcut', function (): void {
    expect(emojis()->convert('Hi :) :wave:', Mode::Emoji, Mode::Emoticon, Mode::Shortcode))->toBe('Hi 🙂 👋');
});

it('refuses a target-only mode as a source', function (): void {
    emojis()->text('x')->from(Mode::Name)->toEmoji();
})->throws(UnsupportedConversion::class);
