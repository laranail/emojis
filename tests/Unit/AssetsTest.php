<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Render\Renderer;
use Simtabi\Laranail\Emojis\Core\Exceptions\DatasetException;

// The stylesheet and the markup meet at class names, which nothing type-checks. These tests read both sides
// — the committed build and what the renderer writes — so a rename on one side fails here, not on a page.
// `npm run assets-check` separately proves the build matches its SCSS source.

/** @return list<string> every class selector in the built stylesheet */
function builtClasses(string $name = 'emojis'): array
{
    preg_match_all('/\.([a-z][\w-]*)/', Emojis::create()->stylesheet($name), $matches);

    return array_values(array_unique($matches[1]));
}

/** @return list<string> */
function classesIn(string $html): array
{
    preg_match_all('/\bclass="([^"]*)"/', $html, $matches);

    return array_values(array_unique(array_merge(...array_map(static fn (string $c): array => preg_split('/\s+/', trim($c)) ?: [], $matches[1]))));
}

it('defines every class the renderer writes', function (): void {
    $emojis = Emojis::create();
    $written = [
        ...classesIn($emojis->text('😀')->toImages()),                        // fitted <svg>
        ...classesIn(Emojis::create(['images' => ['fit' => 'none']])->text('😀')->toImages()),            // plain <img>
        ...explode(' ', Renderer::NATIVE_CLASSES),                             // the Blade component's span
        'laranail-emoji-box',                                                  // documented for containers
    ];

    expect($written)->toContain('laranail-emoji', 'laranail-emoji-image', 'laranail-emoji-native');

    foreach (array_unique($written) as $class) {
        expect(builtClasses())->toContain($class);
    }
});

it('defines only prefixed classes, so it cannot restyle anything of the application', function (): void {
    $classes = builtClasses();

    expect($classes)->toHaveCount(4);

    foreach ($classes as $class) {
        expect($class)->toStartWith('laranail-emoji');
    }
});

it('adds configured image classes after its own instead of replacing them', function (): void {
    $html = Emojis::create(['images' => ['class' => 'avatar-emoji', 'fit' => 'none']])->text('😀')->to(Mode::Image);

    expect($html)->toContain('class="laranail-emoji laranail-emoji-image avatar-emoji"');
});

it('builds into public/assets, one directory per kind, with stable names', function (): void {
    expect(Emojis::assetPath('css/emojis.css'))->toBeFile()
        ->and(glob(Emojis::assetPath() . '/*/*'))->each->toMatch('~/public/assets/(css|js|[a-z0-9]+)/[\w.-]+$~')
        ->and(glob(Emojis::assetPath() . '/css/*.css'))->each->not->toMatch('/-[A-Za-z0-9_]{8}\.css$/');
});

it('styles every class the picker module writes, and only prefixed ones', function (): void {
    $css = builtClasses('picker');
    preg_match_all('/`\$\{PREFIX\}(-picker[\w-]*)`/', (string) file_get_contents(dirname(__DIR__, 2) . '/resources/assets/scripts/picker.js'), $m);
    $written = array_values(array_unique(array_map(static fn (string $suffix): string => 'laranail-emoji' . $suffix, $m[1])));

    expect(count($written))->toBeGreaterThan(10);

    foreach ($css as $class) {
        expect($class)->toStartWith('laranail-emoji');
    }

    foreach ($written as $class) {
        expect(in_array($class, $css, true))->toBeTrue("picker.js writes .{$class}, which picker.css does not define");
    }
});

it('refuses a stylesheet name that could leave the assets directory', function (): void {
    expect(static fn (): string => Emojis::create()->stylesheet('../../composer'))->toThrow(DatasetException::class);
});
