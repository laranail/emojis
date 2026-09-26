<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Console\Kernel;

/*
 * Every public name this package registers carries the vendor and the slug, and is asserted against the live
 * registries — not the provider source — so the guard holds whatever the registration code looks like.
 * Modelled on laranail/atlas and laranail/tayari-ui-livewire.
 */

it('reads and publishes config under laranail.emojis', function (): void {
    expect(config('laranail.emojis.image_set'))->toBe('twemoji')
        ->and(config('emojis'))->toBeNull()
        ->and(ServiceProvider::publishableGroups())->toContain('laranail::emojis-config')
        ->and(ServiceProvider::publishableGroups())->not->toContain('emojis-config');
});

it('registers its translation namespace as the composer name', function (): void {
    expect(Lang::getLoader()->namespaces())->toHaveKey('laranail/emojis')
        ->and(Lang::getLoader()->namespaces())->not->toHaveKey('emojis')
        ->and(__('laranail/emojis::validation.no_emoji'))->not->toBe('laranail/emojis::validation.no_emoji');
});

it('registers its Blade component namespace with the hyphenated prefix', function (): void {
    $namespaces = new ReflectionProperty(Blade::getFacadeRoot(), 'classComponentNamespaces')->getValue(Blade::getFacadeRoot());

    expect($namespaces)->toHaveKey('laranail-emojis')
        ->and($namespaces)->not->toHaveKey('emojis')
        ->and($namespaces)->not->toHaveKey('emoji');
});

it('registers its directive under a vendor-scoped name', function (): void {
    $directives = array_keys(Blade::getCustomDirectives());

    expect($directives)->toContain('laranailEmojis')
        ->and($directives)->not->toContain('emoji')
        ->and($directives)->not->toContain('emojis');
});

it('names every command laranail::emojis.*', function (): void {
    $ours = array_values(array_filter(array_keys(app(Kernel::class)->all()), static fn (string $name): bool => str_contains($name, 'emojis')));

    expect($ours)->toHaveCount(4);

    foreach ($ours as $name) {
        expect($name)->toStartWith('laranail::emojis.');
    }
});

it('has teeth: a bare name registered here is visible to the checks above', function (): void {
    Blade::directive('emoji', static fn (): string => '');

    expect(array_keys(Blade::getCustomDirectives()))->toContain('emoji');
});

it('compiles the directive with arguments, with a mode, and bare', function (string $template, string $expected): void {
    expect(Blade::render($template, ['text' => 'hi :wave: <b>']))->toContain($expected);
})->with([
    'images' => ['@laranailEmojis($text)', 'data-laranail-emoji="1F44B"'],
    'native' => ["@laranailEmojis(\$text, 'emoji')", 'hi 👋 &lt;b&gt;'],
    'bare'   => ['[@laranailEmojis]', '[]'],
]);

it('keeps the helper out of the global namespace', function (): void {
    expect(function_exists('emoji'))->toBeFalse()
        ->and(function_exists('Simtabi\Laranail\Emojis\emoji'))->toBeTrue();
});
