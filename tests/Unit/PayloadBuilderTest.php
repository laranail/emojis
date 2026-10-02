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
