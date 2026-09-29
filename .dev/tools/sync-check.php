<?php

declare(strict_types=1);

/**
 * CI gate: fails when any generated file disagrees with what the generators produce today.
 *
 * Runs .dev/tools/build-dataset.php --check (the dataset shards), .dev/tools/generate-enums.php --check (the enums
 * generated from those shards) and .dev/tools/emoticons-doc.php --check (the emoticon table in the docs). Outside CI, a missing source cache is reported as a skip, so a contributor
 * without the upstream files can still lint. In CI the dataset check runs with --fetch, so the gate can
 * never be skipped where it matters: a skip in CI means the gate is silently not running, and is a failure.
 */
$root = dirname(__DIR__, 2);
$inCi = getenv('CI') !== false && getenv('CI') !== '' && getenv('CI') !== 'false';
$php = escapeshellarg(PHP_BINARY);
$failed = false;

$commands = [
    'dataset'       => $php . ' ' . escapeshellarg($root . '/.dev/tools/build-dataset.php') . ' --check' . ($inCi ? ' --fetch' : ''),
    'enums'         => $php . ' ' . escapeshellarg($root . '/.dev/tools/generate-enums.php') . ' --check',
    'emoticons doc' => $php . ' ' . escapeshellarg($root . '/.dev/tools/emoticons-doc.php') . ' --check',
];

foreach ($commands as $name => $command) {
    exec($command . ' 2>&1', $output, $status);
    $text = implode("\n", $output);
    $output = [];

    if ($status !== 0) {
        fwrite(STDERR, "sync-check [{$name}] failed:\n{$text}\n");
        $failed = true;

        continue;
    }

    if ($inCi && str_starts_with($text, 'SKIP')) {
        fwrite(STDERR, "sync-check [{$name}] was skipped in CI, which means the gate is not running:\n{$text}\n");
        $failed = true;

        continue;
    }

    fwrite(STDOUT, $text . "\n");
}

exit($failed ? 1 : 0);
