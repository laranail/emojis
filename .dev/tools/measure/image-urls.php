<?php

declare(strict_types=1);

/**
 * Writes build/cache/images/<set>.urls — "HEXCODE<TAB>URL" for every emoji the set publishes — as the input
 * for downloading and measuring (see measure-bounds.mjs). Run after build-dataset.php, since coverage and the
 * pinned versions come from the committed dataset.
 *
 *   php .dev/tools/measure/image-urls.php twemoji noto openmoji fluent
 */

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Fit;

$root = dirname(__DIR__, 3);

require $root . '/vendor/autoload.php';

$sets = array_slice($argv, 1) ?: ['twemoji', 'noto', 'openmoji', 'fluent'];
$emojis = Emojis::create(['images' => ['fit' => Fit::None->value]]);
@mkdir($root . '/build/cache/images', 0o775, true);

foreach ($sets as $set) {
    $lines = [];

    foreach ($emojis->all() as $emoji) {
        $url = $emoji->imageUrl($set);

        if ($url !== null) {
            $lines[] = $emoji->hexcode . "\t" . $url;
        }
    }

    file_put_contents("{$root}/build/cache/images/{$set}.urls", implode("\n", $lines) . "\n");
    fwrite(STDOUT, sprintf("%s: %d images\n", $set, count($lines)));
}
