<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\Carrier;

it('reads each Japanese carrier private-use emoji into Unicode', function (Carrier $carrier, int $codepoint, string $emoji): void {
    $legacy = 'Hi ' . mb_chr($codepoint, 'UTF-8');

    expect(emojis()->text($legacy)->carrier($carrier)->from(Mode::Carrier)->toEmoji())->toBe('Hi ' . $emoji);
})->with([
    'docomo sun'        => [Carrier::Docomo, 0xE63E, '☀️'],
    'au keycap #'       => [Carrier::Au, 0xEB84, '#️⃣'],
    'SoftBank keycap #' => [Carrier::SoftBank, 0xE210, '#️⃣'],
    'Google keycap #'   => [Carrier::Google, 0xFE82C, '#️⃣'],
]);

it('needs the carrier named, because the carrier ranges overlap', function (): void {
    $data = require dirname(__DIR__, 2) . '/database/generated/carriers.php';
    // A code point both au and SoftBank use, for different emoji — found from the data, not assumed.
    $shared = array_keys(array_filter(
        array_intersect_key($data['au']['reads'], $data['softbank']['reads']),
        static fn (string $hex, int|string $code): bool => $data['softbank']['reads'][$code] !== $hex,
        ARRAY_FILTER_USE_BOTH,
    ));

    expect($shared)->not->toBeEmpty();

    $pua = mb_chr((int) hexdec((string) $shared[0]), 'UTF-8');
    $softbank = emojis()->text($pua)->carrier(Carrier::SoftBank)->from(Mode::Carrier)->toEmoji();
    $au = emojis()->text($pua)->carrier(Carrier::Au)->from(Mode::Carrier)->toEmoji();

    expect($softbank)->not->toBe($pua)
        ->and($au)->not->toBe($pua)
        ->and($softbank)->not->toBe($au);
});

it('writes emoji back as carrier codes and degrades what the carrier never had', function (): void {
    expect(emojis()->text('☀️ 🫩')->carrier(Carrier::Docomo)->to(Mode::Carrier))->toBe(mb_chr(0xE63E, 'UTF-8') . ' 🫩')
        ->and(emojis()->get('👍🏽')->carrierCode(Carrier::SoftBank))->toBe(emojis()->get('👍')->carrierCode(Carrier::SoftBank));
});

it('round-trips every carrier code: carrier → emoji → carrier', function (Carrier $carrier): void {
    $data = require dirname(__DIR__, 2) . '/database/generated/carriers.php';
    $checked = 0;

    foreach ($data[$carrier->value]['reads'] as $code => $hex) {
        $char = mb_chr((int) hexdec((string) $code), 'UTF-8');
        $emoji = emojis()->text($char)->carrier($carrier)->from(Mode::Carrier)->toEmoji();

        expect(emojis()->text($emoji)->carrier($carrier)->to(Mode::Carrier))->toBe($char, "{$carrier->value} {$code}");
        $checked++;
    }

    // docomo had the smallest set: 245 distinct codes.
    expect($checked)->toBeGreaterThan(240);
})->with(Carrier::cases());
