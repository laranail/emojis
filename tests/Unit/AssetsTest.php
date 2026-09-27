<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;

// The committed build must carry every class the SCSS source declares. `npm run assets-check` is the exact
// comparison and needs Node; this is the check the PHP suite can make on its own.

/** @return list<string> */
function scssClasses(): array
{
    $source = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/assets/styles/emojis.scss');
    $source = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $source);
    preg_match_all('/\.([a-z][\w-]*)/', $source, $matches);

    return array_values(array_unique($matches[1]));
}

it('ships a built stylesheet for every class the SCSS source declares', function (): void {
    $css = Emojis::create()->stylesheet();
    $classes = scssClasses();

    expect($classes)->toHaveCount(3);

    foreach ($classes as $class) {
        expect(preg_match('/\.' . preg_quote($class, '/') . '(?![\w-])/', $css))->toBe(1, "missing .{$class}");
    }
});

it('builds into public/assets, one directory per kind, with stable names', function (): void {
    expect(Emojis::assetPath('css/emojis.css'))->toBeFile()
        ->and(glob(Emojis::assetPath() . '/*/*'))->each->toMatch('~/public/assets/(css|js|[a-z0-9]+)/[\w.-]+$~')
        ->and(glob(Emojis::assetPath() . '/css/*.css'))->each->not->toMatch('/-[A-Za-z0-9_]{8}\.css$/');
});
