<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Picker\PickerPayload;
use Simtabi\Laranail\Emojis\Core\Picker\PayloadBuilder;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;

const PIXEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

$build = static fn (array $config = [], ?string $locale = null): PickerPayload => new PayloadBuilder(Emojis::create($config, terminal: new EnvTerminalProbe(override: true)))->build($locale);
$hexcodes = static fn ($payload): array => array_merge(...array_map(static fn (array $g): array => array_column($g['emoji'], 'hexcode'), $payload->groups));

it('lists every base emoji once, grouped in CLDR order, without components or tone variants', function () use ($build, $hexcodes): void {
    $payload = $build();
    $all = $hexcodes($payload);

    expect($payload->groups[0]['slug'])->toBe('smileys_and_emotion')
        ->and(array_column($payload->groups, 'slug'))->not->toContain('component')
        ->and(count($all))->toBe(count(array_unique($all)))
        ->and(count($all))->toBe(emojis()->query()->count())
        ->and($all)->not->toContain('1F44B-1F3FD');
});

it('carries localized names and keywords, the shortcode and the skin-tone map', function () use ($build): void {
    $entries = array_merge(...array_column($build(locale: 'fr')->groups, 'emoji'));
    $wave = $entries[array_search('1F44B', array_column($entries, 'hexcode'), true)];

    expect($wave['name'])->toBe('signe de la main')
        ->and($wave['shortcode'])->toBe('wave')
        ->and($wave['skins'])->toHaveCount(5);
});

it('applies the policy: denied groups and emoji, the version cap, and custom emoji', function () use ($build, $hexcodes): void {
    $payload = $build(['policy' => ['deny_groups' => ['flags'], 'deny' => ['1F680'], 'max_version' => '13.0']]);
    $all = $hexcodes($payload);

    expect(array_column($payload->groups, 'slug'))->not->toContain('flags')
        ->and($all)->not->toContain('1F680')
        ->and($all)->not->toContain('1FAE9')
        ->and($all)->toContain('1F600');

    $custom = Emojis::create(terminal: new EnvTerminalProbe(override: true))->addCustom('partyparrot', PIXEL);
    $blocked = Emojis::create(['policy' => ['allow_custom' => false]], terminal: new EnvTerminalProbe(override: true))->addCustom('partyparrot', PIXEL);

    expect(new PayloadBuilder($custom)->build()->custom)->toBe([['name' => 'partyparrot', 'label' => 'partyparrot', 'image' => PIXEL, 'fallback' => null]])
        ->and(new PayloadBuilder($blocked)->build()->custom)->toBe([]);
});

$entry = static function (PickerPayload $payload, string $hexcode): ?array {
    foreach ($payload->groups as $group) {
        foreach ($group['emoji'] as $emoji) {
            if ($emoji['hexcode'] === $hexcode) {
                return $emoji;
            }
        }
    }

    return null;
};

it('applies the version cap to every skin-tone variant, not only the base', function () use ($build, $entry): void {
    // 🤝 is Emoji 3.0; its toned forms arrived in 14.0. Under a 13.0 cap the base stays and every tone goes.
    $handshake = $entry($build(['policy' => ['max_version' => '13.0']]), '1F91D');

    expect($handshake)->not->toBeNull()
        ->and($handshake['skins'])->toBe([])
        ->and($handshake)->not->toHaveKey('base');
});

it('reports the version of each variant newer than its base', function () use ($build, $entry): void {
    $handshake = $entry($build(), '1F91D');

    // 👋 is 0.6 and its tones 1.0: one shared version collapses to a string.
    expect($handshake['skin_versions'])->toBe('14.0')
        ->and($entry($build(), '1F44B')['skin_versions'])->toBe('1.0')
        ->and($entry($build(), '1F600'))->not->toHaveKey('skin_versions');
});

it('drops a denied variant from the skins map and keeps the others', function () use ($build, $entry): void {
    $thumbs = $entry($build(['policy' => ['deny' => ['1F44D-1F3FF']]]), '1F44D');

    expect($thumbs['skins'])->not->toContain('1F44D-1F3FF')
        ->and($thumbs['skins'])->toHaveCount(4);
});

it('lists a base whose only permitted forms are variants, marked so a picker offers only those', function () use ($build, $entry, $hexcodes): void {
    $payload = $build(['policy' => ['allow_only' => ['1F44D-1F3FD']]]);
    $thumbs = $entry($payload, '1F44D');

    expect($hexcodes($payload))->toBe(['1F44D'])
        ->and($thumbs['base'])->toBeFalse()
        ->and($thumbs['skins'])->toBe(['3' => '1F44D-1F3FD']);
});

it('offers nothing the sanitizer would strip', function (): void {
    $config = ['policy' => ['max_version' => '13.0', 'deny' => ['1F44D-1F3FF']]];
    $emojis = Emojis::create($config, terminal: new EnvTerminalProbe(override: true));
    $payload = new PayloadBuilder($emojis)->build();
    $offered = [];

    foreach ($payload->groups as $group) {
        foreach ($group['emoji'] as $emoji) {
            if (($emoji['base'] ?? true) !== false) {
                $offered[] = $emoji['hexcode'];
            }

            array_push($offered, ...array_values($emoji['skins']));
        }
    }

    $refused = array_filter($offered, static fn (string $hex): bool => ! $emojis->options()->policy->permits($emojis->fromHexcode($hex)));

    expect(count($offered))->toBeGreaterThan(1500)
        ->and($refused)->toBe([]);
});
