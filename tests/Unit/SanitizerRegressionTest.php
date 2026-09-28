<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Security\Threat;

/*
 * secureRun() looked back at the output with mb_substr($before, -1), rescanning everything written so far
 * once per run: "a😀" x N took 2.85 s at 100 KB, and 64 s at 500 KB. At 250 KB the linear cost is about 1 s and
 * the quadratic one about 18 s; the bound sits well clear of both.
 */
it('sanitizes 250 KB of alternating text and emoji in linear time', function (): void {
    $text = str_repeat('a😀', 50_000);
    $start = hrtime(true);

    $clean = emojis()->sanitize($text)->clean();

    expect($clean)->toBe($text)
        ->and((hrtime(true) - $start) / 1e9)->toBeLessThan(10.0);
});

it('does not pass input longer than the size limit as safe', function (): void {
    $emojis = Emojis::create(['input' => ['max_bytes' => 16]]);
    $input = str_repeat('a', 16) . "\u{202E}evil";
    $result = $emojis->sanitize($input)->run();

    expect($emojis->sanitize($input)->isSafe())->toBeFalse()
        ->and($result->report->has(Threat::Oversized))->toBeTrue()
        ->and($result->text)->toBe(str_repeat('a', 16));
});

it('treats input exactly at the size limit as ordinary', function (): void {
    $emojis = Emojis::create(['input' => ['max_bytes' => 16]]);

    expect($emojis->sanitize(str_repeat('a', 16))->isSafe())->toBeTrue();
});

it('keeps at most four joiners in a row across a chain of known emoji', function (): void {
    $chain = implode("\u{200D}", array_fill(0, 10, '😀'));
    $result = emojis()->sanitize($chain)->run();

    expect(substr_count($result->text, "\u{200D}"))->toBe(4)
        ->and($result->report->count(Threat::Joiner))->toBe(5)
        ->and(str_replace("\u{200D}", '', $result->text))->toBe(str_repeat('😀', 10));
});

it('starts counting joiners again after ordinary text', function (): void {
    $chain = implode("\u{200D}", array_fill(0, 4, '😀'));
    $input = $chain . ' ' . $chain;

    expect(emojis()->sanitize($input)->clean())->toBe($input);
});

it('removes bidi marks', function (int $cp): void {
    $result = emojis()->sanitize('a' . mb_chr($cp, 'UTF-8') . 'b')->run();

    expect($result->text)->toBe('ab')
        ->and($result->report->count(Threat::Bidi))->toBe(1);
})->with([
    'LRM' => 0x200E,
    'RLM' => 0x200F,
    'ALM' => 0x061C,
]);

it('keeps bidi marks when bidi controls are kept', function (): void {
    expect(emojis()->sanitize("a\u{200F}b")->keepBidiControls()->clean())->toBe("a\u{200F}b");
});

it('removes invisible characters', function (int $cp): void {
    $result = emojis()->sanitize('a' . mb_chr($cp, 'UTF-8') . 'b')->run();

    expect($result->text)->toBe('ab')
        ->and($result->report->count(Threat::Invisible))->toBe(1);
})->with([
    'soft hyphen'               => 0x00AD,
    'combining grapheme joiner' => 0x034F,
    'line separator'            => 0x2028,
    'paragraph separator'       => 0x2029,
    'braille blank'             => 0x2800,
    'interlinear anchor'        => 0xFFF9,
    'interlinear separator'     => 0xFFFA,
    'interlinear terminator'    => 0xFFFB,
    'musical begin beam'        => 0x1D173,
    'musical end phrase'        => 0x1D17A,
    'khmer inherent aq'         => 0x17B4,
    'khmer inherent aa'         => 0x17B5,
    'hangul choseong filler'    => 0x115F,
    'hangul jungseong filler'   => 0x1160,
    'hangul filler'             => 0x3164,
    'halfwidth hangul filler'   => 0xFFA0,
]);

it('still keeps joiners and selectors inside real emoji sequences', function (): void {
    $input = "family 👨\u{200D}👩\u{200D}👧\u{200D}👦 heart ❤\u{FE0F}\u{200D}🔥 flag 🏴\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F}";

    expect(emojis()->sanitize($input)->isSafe())->toBeTrue();
});
