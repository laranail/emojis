<?php

declare(strict_types=1);

/*
 * Builds the picker showcase: a single page with every picker feature live, rendered by the package's own
 * Blade components from the current config, payload and built assets.
 *
 *   composer demo            writes .dev/demo/index.html
 *   php -S 127.0.0.1:8765 -t .dev/demo
 *
 * The page embeds the payload once (about 1 MB with kaomoji and symbols), so it is built, not committed.
 * Image sets load from their CDNs; everything else is inline.
 */

use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\Foundation\Application;
use Simtabi\Laranail\Emojis\Providers\EmojisServiceProvider;

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';

$app = Application::create(basePath: null);
$app['config']->set('app.url', 'http://localhost');

// Every optional feature on, so the page shows all of them.
foreach (['kaomoji', 'symbols', 'set_switcher', 'autocomplete'] as $feature) {
    $app['config']->set("laranail.emojis.picker.features.{$feature}", true);
}

$app->register(EmojisServiceProvider::class);

$template = file_get_contents(__DIR__ . '/showcase.blade.php');

if ($template === false) {
    fwrite(STDERR, "Cannot read showcase.blade.php\n");
    exit(1);
}

$html = Blade::render($template, ['sets' => ['twemoji', 'noto', 'openmoji']]);
$out = __DIR__ . '/index.html';

if (file_put_contents($out, $html) === false) {
    fwrite(STDERR, "Cannot write {$out}\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Wrote %s (%d KB)\n", $out, intdiv(strlen($html), 1024)));
