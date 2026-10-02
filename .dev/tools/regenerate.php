<?php

declare(strict_types=1);

/**
 * Runs every generator in order and writes their output: the dataset shards, the enums, and the docs built
 * from the dataset. After it, `composer sync-check` passes by construction.
 *
 *   php .dev/tools/regenerate.php
 *
 * Needs the cached sources under build/cache/sources (php .dev/tools/build-dataset.php --fetch).
 */
require __DIR__ . '/lib/Generators.php';

$php = escapeshellarg(PHP_BINARY);

foreach (Generators::all() as $label => $script) {
    passthru($php . ' ' . escapeshellarg(__DIR__ . '/' . $script), $status);

    if ($status !== 0) {
        fwrite(STDERR, "regenerate [{$label}] failed\n");
        exit(1);
    }
}
