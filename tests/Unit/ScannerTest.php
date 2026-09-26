<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidInput;

it('leaves colon-heavy text that is not a shortcode alone', function (string $text): void {
    expect(emojis()->text($text)->toEmoji())->toBe($text);
})->with([
    'clock'           => 'meet at 12:30:45',
    'c++ scope'       => 'std::vector<int>',
    'artisan command' => 'php artisan laranail::emojis.search',
    'route parameter' => '/users/:id:/posts',
    'unknown code'    => ':not_a_real_shortcode:',
    'glued to a word' => 'abc:smile:',
    'glued after'     => ':smile:abc',
]);

it('matches back-to-back shortcodes and preset aliases', function (): void {
    expect(emojis()->text(':smile::smile: :+1: :-1: :100: :t-rex: :8ball:')->toEmoji())->toBe('😄😄 👍 👎 💯 🦖 🎱');
});

it('matches emoticons only as whole words', function (string $text, string $expected): void {
    expect(emojis()->text($text)->withEmoticons()->toEmoji())->toBe($expected);
})->with([
    'plain'                => [':) <3 ;)', '🙂 ❤️ 😉'],
    'url'                  => ['see http://example.com', 'see http://example.com'],
    'code'                 => ['foo();) bar', 'foo();) bar'],
    'glued'                => ['x<3 a:p', 'x<3 a:p'],
    'trailing punctuation' => ['nice :).', 'nice 🙂.'],
    'risky left alone'     => ['XD D: 8) B-)', 'XD D: 8) B-)'],
]);

it('matches risky emoticons when asked', function (): void {
    expect(emojis()->text('XD 8)')->withEmoticons(risky: true)->toEmoji())->toBe('😆 😎');
});

it('does not treat text-default characters as emoji unless asked', function (): void {
    expect(emojis()->text('© 2026 ACME™')->count())->toBe(0)
        ->and(emojis()->text('© 2026 ACME™')->includeTextPresentation()->count())->toBe(2)
        ->and(emojis()->text("©\u{FE0F}")->count())->toBe(1);
});

it('reads keycaps with and without FE0F', function (): void {
    expect(emojis()->text("#\u{FE0F}\u{20E3} 1\u{20E3}")->toAscii())->toBe(':hash: :one:');
});

it('prefers the longest sequence', function (): void {
    $matches = emojis()->text('👨‍👩‍👧‍👦❤️‍🔥')->extract();

    expect($matches)->toHaveCount(2)
        ->and($matches[0]->emoji?->hexcode)->toBe('1F468-200D-1F469-200D-1F467-200D-1F466')
        ->and($matches[1]->emoji?->hexcode)->toBe('2764-FE0F-200D-1F525');
});

it('decodes runs of escapes into as many emoji as they hold', function (): void {
    expect(emojis()->text('&#x1F468;&#x200D;&#x1F4BB; &#128512;&#128513;')->from(Mode::HtmlEntity)->toEmoji())->toBe('👨‍💻 😀😁');
});

it('finds pictographs the dataset does not know, for sanitising', function (): void {
    // A code point Unicode has reserved for future emoji (Extended_Pictographic, not yet assigned), found
    // at runtime so the test keeps working when a dataset upgrade assigns the one it used to pick.
    $reserved = null;

    for ($cp = 0x1FAFF; $cp > 0x1FA70 && $reserved === null; $cp--) {
        $reserved = emojis()->fromChar(mb_chr($cp, 'UTF-8')) instanceof Emoji ? null : mb_chr($cp, 'UTF-8');
    }

    expect($reserved)->not->toBeNull();

    $text = "a {$reserved} b";

    expect(emojis()->text($text)->count())->toBe(0)
        ->and(emojis()->text($text)->count(includeUnknown: true))->toBe(1)
        ->and(emojis()->strip($text))->toBe('a  b');
});

it('strips a vendor ZWJ sequence without leaving the joiner behind', function (): void {
    // 🐱‍👤 is not RGI: platforms show 🐱 and 👤, and so does the scanner.
    expect(emojis()->text('a 🐱‍👤 b')->count())->toBe(2)
        ->and(emojis()->strip('a 🐱‍👤 b'))->toBe('a  b')
        ->and(emojis()->strip("x \u{1F3FD} y"))->toBe('x  y');
});

it('never strips a zero-width joiner that belongs to a script', function (): void {
    $hindi = "क्\u{200D}ष";

    expect(emojis()->strip($hindi))->toBe($hindi);
});

it('reports offsets in bytes and UTF-16 units', function (): void {
    $match = emojis()->text('é 😀')->extract()[0];

    expect($match->offset)->toBe(3)
        ->and($match->utf16Offset())->toBe(2)
        ->and($match->text)->toBe('😀');
});

it('measures emoji as one character and two columns whatever the ICU version', function (): void {
    $text = emojis()->text('a👨‍👩‍👧‍👦b🫱🏻‍🫲🏿');

    expect($text->length())->toBe(4)
        ->and($text->width())->toBe(6)
        ->and(emojis()->text('ab👨‍👩‍👧‍👦cd')->truncate(4, '…'))->toBe('ab…');
});

it('rejects invalid UTF-8 without echoing it', function (): void {
    emojis()->text("bad \xC3\x28 bytes");
})->throws(InvalidInput::class, 'Input is not valid UTF-8.');

it('rejects input over the size limit', function (): void {
    Emojis::create(['max_input_bytes' => 10])->text(str_repeat('a', 11));
})->throws(InvalidInput::class);

it('answers only-emoji and contains', function (): void {
    expect(emojis()->isOnlyEmoji(' 🚀 👋🏽 '))->toBeTrue()
        ->and(emojis()->isOnlyEmoji('🚀 go'))->toBeFalse()
        ->and(emojis()->isOnlyEmoji('   '))->toBeFalse()
        ->and(emojis()->contains('plain'))->toBeFalse();
});
