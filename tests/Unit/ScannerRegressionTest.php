<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Text\Token;
use Simtabi\Laranail\Emojis\Core\Text\ScanOptions;

/*
 * pictographs() compared every candidate against every known token, so text made only of emoji was
 * quadratic: 80 KB of 😀 took 37 s to count and 320 KB took 130 s to strip. At 100 KB the linear cost is
 * about 1.5 s and the quadratic one over a minute; the bound sits well clear of both.
 */
it('counts and strips 100 KB of emoji in linear time', function (): void {
    $text = str_repeat('😀', 25_000);
    $start = hrtime(true);

    $count = emojis()->text($text)->count(true);
    $stripped = emojis()->text($text)->strip();

    expect($count)->toBe(25_000)
        ->and($stripped)->toBe('')
        ->and((hrtime(true) - $start) / 1e9)->toBeLessThan(15.0);
});

it('still excludes pictographs that overlap a known token, whatever order the tokens arrive in', function (): void {
    $scanner = emojis()->scanner();
    $text = "🐱\u{200D}👤 x 🫨\u{200D}🫨";
    $known = $scanner->scan($text, new ScanOptions);

    $unknown = $scanner->pictographs($text, array_reverse($known));

    expect(array_map(static fn (Token $t): string => $t->text($text), $unknown))
        ->toBe(array_map(static fn (Token $t): string => $t->text($text), $scanner->pictographs($text, $known)));
});

it('treats a standardized VS16 after a default-emoji character as part of the emoji', function (): void {
    // "⭐️" is 2B50 FE0F: valid per emoji-variation-sequences.txt, but emoji-test.txt lists only 2B50.
    $text = "a \u{2B50}\u{FE0F} b \u{2615}\u{FE0F} c \u{2B50} d";

    expect(emojis()->count($text))->toBe(3)
        ->and(emojis()->strip($text))->toBe('a  b  c  d')
        ->and(emojis()->text($text)->toShortcodes())->toBe('a :star: b :coffee: c :star: d')
        ->and(emojis()->find("\u{2B50}\u{FE0F}")?->hexcode)->toBe('2B50');
});
