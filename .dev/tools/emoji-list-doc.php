<?php

declare(strict_types=1);

/**
 * Writes the browsable emoji list in docs/tools/ from the generated dataset: docs/tools/emoji-list.md (the
 * index) and one docs/tools/emoji-list-<group>.md per Unicode group.
 *
 *   php .dev/tools/emoji-list-doc.php            rewrite the pages
 *   php .dev/tools/emoji-list-doc.php --check    exit 1 if any page differs from what the dataset produces
 *
 * Generated rather than written, like docs/tools/emoticons.md: a hand-kept list of almost two thousand
 * emoji goes stale with the next Unicode release. Skin-tone variants are not listed separately; a column
 * says whether the emoji takes tones.
 */

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;

$root = dirname(__DIR__, 2);
$check = in_array('--check', $argv, true);

require $root . '/vendor/autoload.php';

$emojis = Emojis::create();
$footer = "---\n\n[← Docs index](../../README.md#documentation)\n";
$cell = static fn (string $text): string => str_replace('|', '\|', $text);

/** @var array<string, array{label: string, rows: list<Emoji>}> $groups group value => label and its emoji */
$groups = [];

foreach ($emojis->catalogue()->all() as $emoji) {
    if ($emoji->baseHexcode !== null) {
        continue; // a skin-tone variant; its base carries the Skin tones column
    }

    $groups[$emoji->group->value] ??= ['label' => $emoji->group->label(), 'rows' => []];
    $groups[$emoji->group->value]['rows'][] = $emoji;
}

$pages = [];
$index = [];
$total = 0;

foreach ($groups as $value => $group) {
    $file = 'emoji-list-' . str_replace('_', '-', $value) . '.md';
    $count = count($group['rows']);
    $total += $count;
    $index[] = sprintf('| [%s](%s) | %s | %s |', $cell($group['label']), $file, number_format($count), implode(' ', array_map(static fn (Emoji $e): string => $e->char, array_slice($group['rows'], 0, 8))));

    $lines = [
        "# Emoji: {$group['label']}",
        '',
        sprintf('The %s emoji in the Unicode group *%s*, in the order emoji keyboards show them.', number_format($count), $group['label']),
        '',
        'Generated from the dataset by `.dev/tools/emoji-list-doc.php`; `composer sync-check` fails when it drifts. The [emoji list](emoji-list.md) has every group.',
        '',
        '| Emoji | Name | Shortcode | Since | Skin tones |',
        '|---|---|---|---|---|',
    ];

    foreach ($group['rows'] as $emoji) {
        $shortcode = $emoji->shortcode();
        $lines[] = sprintf(
            '| %s | %s | %s | %s | %s |',
            $emoji->char,
            $cell($emoji->name()),
            $shortcode === null ? '—' : '`:' . $cell($shortcode) . ':`',
            $emoji->version->value,
            $emoji->supportsSkinTones() ? '✓' : '—',
        );
    }

    $pages[$file] = implode("\n", $lines) . "\n\n" . $footer;
}

$pages['emoji-list.md'] = implode("\n", [
    '# Emoji list',
    '',
    sprintf('All %s emoji, one page per Unicode group; with their skin-tone variants the catalogue holds %s.', number_format($total), number_format(count($emojis->catalogue()->all()))),
    '',
    'Each page gives the emoji, its CLDR name, its shortcode and the Unicode version that added it. Skin-tone',
    'variants are not listed separately: the *Skin tones* column says whether an emoji takes them, and',
    '`Emoji::skinToneVariants()` returns them. Generated from the dataset by `.dev/tools/emoji-list-doc.php`;',
    '`composer sync-check` fails when it drifts.',
    '',
    '| Group | Emoji | First few |',
    '|---|---|---|',
    ...$index,
    '',
    'In code: `Emojis::all()` returns them all, `Emojis::query()->group(Group::SmileysAndEmotion)` one group,',
    'and `laranail::emojis.export` writes them as JSON. The [catalogue](catalogue.md) describes each field.',
    '',
]) . "\n" . $footer;

$dir = $root . '/docs/tools';
$stale = array_diff(array_map('basename', glob($dir . '/emoji-list-*.md') ?: []), array_keys($pages));
$differs = array_keys(array_filter($pages, static fn (string $text, string $file): bool => @file_get_contents("{$dir}/{$file}") !== $text, ARRAY_FILTER_USE_BOTH));
$summary = sprintf('%d emoji in %d groups', $total, count($groups));

if ($check) {
    if ($differs !== [] || $stale !== []) {
        fwrite(STDERR, 'emoji list docs out of date (' . implode(', ', [...$differs, ...$stale]) . "); run php .dev/tools/emoji-list-doc.php\n");
        exit(1);
    }

    fwrite(STDOUT, "emoji list docs in sync: {$summary}\n");
    exit(0);
}

foreach ($pages as $file => $text) {
    file_put_contents("{$dir}/{$file}", $text);
}

foreach ($stale as $file) {
    unlink("{$dir}/{$file}"); // a group Unicode no longer has; the page is generated, so it goes with it
}

fwrite(STDOUT, 'Wrote ' . count($pages) . " emoji list pages: {$summary}\n");
