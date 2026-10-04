<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Picker\PickerImages;
use Simtabi\Laranail\Emojis\Core\Picker\PayloadBuilder;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;

/**
 * The browser rebuilds image URLs from a rule (picker.ts IMAGE_RULES) instead of receiving one per emoji, so
 * the two implementations must agree. This writes nothing: it checks that tests/js/fixtures/image-urls.json,
 * which the Vitest suite compares the browser's rules against, still matches what the server produces.
 * Regenerate it with LARANAIL_EMOJIS_WRITE_FIXTURES=1 vendor/bin/pest tests/Unit/PickerImagesTest.php.
 */
const SAMPLES = [
    '1F600', '00A9-FE0F', '2764-FE0F', '1F441-FE0F-200D-1F5E8-FE0F', '1F44B-1F3FD', '1F91D-1F3FB',
    '1FAF1-1F3FB-200D-1FAF2-1F3FF', '1F468-200D-1F469-200D-1F467', '0023-FE0F-20E3', '1F1FA-1F1F8',
    '1F3F4-E0067-E0062-E0065-E006E-E0067-E007F', '263A-FE0F', '1F9D1-1F3FD-200D-1F680', '1F6B6-200D-2642-FE0F',
];

$emojis = static fn (): Emojis => Emojis::create(terminal: new EnvTerminalProbe(override: true));

it('builds the same image URLs the browser rebuilds from a rule', function () use ($emojis): void {
    $e = $emojis();
    $expected = [];

    foreach (['twemoji', 'noto', 'openmoji', 'joypixels'] as $name) {
        $set = $e->images()->get($name);
        // What the payload would carry for this set: the browser rebuilds each URL from it.
        $expected['sets'][$name] = new PickerImages($e)->describe($name, SAMPLES);

        foreach (SAMPLES as $hex) {
            $emoji = $e->fromHexcode($hex);

            expect($emoji)->not->toBeNull($hex);

            $expected['urls'][$name][$hex] = $set->url($emoji);
        }
    }

    $file = __DIR__ . '/../js/fixtures/image-urls.json';
    $json = json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

    if (getenv('LARANAIL_EMOJIS_WRITE_FIXTURES') === '1') {
        file_put_contents($file, $json);
    }

    expect($file)->toBeFile()
        ->and(file_get_contents($file))->toBe($json, 'tests/js/fixtures/image-urls.json is stale: regenerate it (see the docblock).');
});

it('describes a rule-based set in a few bytes, and lists only what it lacks', function () use ($emojis): void {
    $images = new PickerImages($emojis())->describe('twemoji', ['1F600', '1FAEB', '1F44B-1F3FD']);

    expect($images)->toMatchArray(['set' => 'twemoji', 'rule' => 'twemoji', 'suffix' => '.svg'])
        ->and($images['base'])->toStartWith('https://cdn.jsdelivr.net/gh/jdecked/twemoji@')
        ->and($images['missing'])->toBe(['1FAEB'])
        ->and(new PickerImages($emojis())->describe('no-such-set', ['1F600']))->toBeNull();
});

it('sends a set with no browser rule as paths below its base', function () use ($emojis): void {
    $fluent = new PickerImages($emojis())->describe('fluent', ['1F600']);

    expect($fluent)->not->toHaveKey('rule')
        ->and($fluent['paths']['1F600'])->toBe('Grinning%20face/Color/grinning_face_color.svg');
});

it('adds the image set to the payload when asked, and every switchable set for the switcher', function () use ($emojis): void {
    $builder = new PayloadBuilder($emojis());

    expect($builder->build()->jsonSerialize())->not->toHaveKeys(['images', 'imageSets'])
        ->and($builder->build(imageSet: 'twemoji')->images['set'])->toBe('twemoji')
        ->and(array_column($builder->build(imageSet: 'twemoji', switchSets: ['twemoji', 'noto', 'openmoji'])->imageSets, 'set'))->toBe(['twemoji', 'noto', 'openmoji'])
        ->and(strlen(json_encode($builder->build(imageSet: 'twemoji'), JSON_THROW_ON_ERROR)) - strlen(json_encode($builder->build(), JSON_THROW_ON_ERROR)))->toBeLessThan(2_000);
});
