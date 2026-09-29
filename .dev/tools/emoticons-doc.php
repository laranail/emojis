<?php

declare(strict_types=1);

/**
 * Writes the emoticon table in docs/tools/emoticons.md from the generated dataset.
 *
 *   php .dev/tools/emoticons-doc.php            rewrite the table between its markers
 *   php .dev/tools/emoticons-doc.php --check    exit 1 if the page differs from what the dataset produces
 *
 * The page lists every emoticon the scanner can match, so it is generated rather than written: a hand-kept
 * list of about two hundred entries drifts the first time the overlay or an upstream source changes.
 */
$root = dirname(__DIR__, 2);
$page = $root . '/docs/tools/emoticons.md';
$check = in_array('--check', $argv, true);

require $root . '/vendor/autoload.php';

$data = require $root . '/database/generated/emoticons.php';
$risky = array_fill_keys($data['risky'], true);
$emojis = Simtabi\Laranail\Emojis\Core\Emojis::create();

// One row per emoji, in catalogue order, with its primary emoticon first.
$rows = [];

foreach ($data['map'] as $emoticon => $hex) {
    $rows[$hex] ??= ['plain' => [], 'risky' => []];
    $rows[$hex][isset($risky[$emoticon]) ? 'risky' : 'plain'][] = (string) $emoticon;
}

$position = [];

foreach ($emojis->catalogue()->all() as $index => $emoji) {
    $position[$emoji->hexcode] = $index;
}

uksort($rows, static fn (string $a, string $b): int => ($position[$a] ?? PHP_INT_MAX) <=> ($position[$b] ?? PHP_INT_MAX));

// A pipe ends a table cell even inside a code span, so it is escaped.
$code = static fn (string $emoticon): string => '`' . str_replace('|', '\|', $emoticon) . '`';

$lines = ['| Emoji | Name | Emoticons | Opt-in only |', '|---|---|---|---|'];

foreach ($rows as $hex => $row) {
    $primary = $data['primary'][$hex] ?? null;
    usort($row['plain'], static fn (string $a, string $b): int => [$a !== $primary, $a] <=> [$b !== $primary, $b]);
    sort($row['risky'], SORT_STRING);

    $plain = array_map(static fn (string $e): string => $e === $primary ? '**' . $code($e) . '**' : $code($e), $row['plain']);
    $emoji = $emojis->get($hex);

    $lines[] = sprintf(
        '| %s | %s | %s | %s |',
        $emoji->char,
        $emoji->name(),
        $plain === [] ? '—' : implode(' ', $plain),
        $row['risky'] === [] ? '—' : implode(' ', array_map($code, $row['risky'])),
    );
}

$table = implode("\n", $lines);
$current = (string) file_get_contents($page);
$pattern = '/(<!-- emoticons:start -->\n)(?:.*?\n)?(<!-- emoticons:end -->)/s';

if (preg_match($pattern, $current) !== 1) {
    fwrite(STDERR, "{$page} has no emoticons:start/end markers\n");
    exit(1);
}

$updated = (string) preg_replace_callback($pattern, static fn (array $m): string => $m[1] . $table . "\n" . $m[2], $current);
$summary = sprintf('%d emoticons for %d emoji, %d opt-in only', count($data['map']), count($rows), count($risky));

if ($check) {
    if ($updated !== $current) {
        fwrite(STDERR, "docs/tools/emoticons.md is out of date ({$summary}); run php .dev/tools/emoticons-doc.php\n");
        exit(1);
    }

    fwrite(STDOUT, "emoticons doc in sync: {$summary}\n");
    exit(0);
}

file_put_contents($page, $updated);
fwrite(STDOUT, "Wrote docs/tools/emoticons.md: {$summary}\n");
