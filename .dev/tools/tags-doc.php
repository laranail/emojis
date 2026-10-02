<?php

declare(strict_types=1);

/**
 * Writes the tag tables in docs/tools/tags.md from the generated dataset.
 *
 *   php .dev/tools/tags-doc.php            rewrite the tables between their markers
 *   php .dev/tools/tags-doc.php --check    exit 1 if the page differs from what the dataset produces
 *
 * Generated for the same reason as the emoticon list: the page has to name every tag and alias the code
 * accepts, and a hand-kept copy drifts the first time tags.json changes.
 */
$root = dirname(__DIR__, 2);
$page = $root . '/docs/tools/tags.md';
$check = in_array('--check', $argv, true);

require $root . '/vendor/autoload.php';

$tags = Simtabi\Laranail\Emojis\Core\Emojis::create()->tags();
$map = (require $root . '/database/generated/tags.php')['map'];
$code = static fn (string $text): string => '`' . str_replace('|', '\|', $text) . '`';
$lines = [];

foreach ($tags->groups() as $slug => $label) {
    $lines[] = "### {$label}";
    $lines[] = '';
    $lines[] = '| Tag | Symbol | Role | Stands for | Written for | Aliases |';
    $lines[] = '|---|---|---|---|---|---|';

    foreach ($tags->group($slug) as $tag) {
        // Stands for: the tag's emoji, primary first. Written for: every emoji Mode::Tag writes as this tag.
        $written = array_map(strval(...), array_keys(array_filter($map, static fn (string $l): bool => $l === $tag->label)));
        $lines[] = sprintf(
            '| %s | %s | %s | %s | %s | %s |',
            $code($tag->text()),
            $tag->symbol,
            $tag->role->value,
            implode(' ', $tag->emoji),
            implode(' ', $written),
            $tag->aliases === [] ? '—' : implode(' ', array_map($code, $tag->aliases)),
        );
    }

    $lines[] = '';
}

$tables = rtrim(implode("\n", $lines));
$current = (string) file_get_contents($page);
$pattern = '/(<!-- tags:start -->\n)(?:.*?\n)?(<!-- tags:end -->)/s';

if (preg_match($pattern, $current) !== 1) {
    fwrite(STDERR, "{$page} has no tags:start/end markers\n");
    exit(1);
}

$updated = (string) preg_replace_callback($pattern, static fn (array $m): string => $m[1] . $tables . "\n" . $m[2], $current);
$summary = sprintf('%d tags in %d groups', count($tags->all()), count($tags->groups()));

if ($check) {
    if ($updated !== $current) {
        fwrite(STDERR, "docs/tools/tags.md is out of date ({$summary}); run php .dev/tools/tags-doc.php\n");
        exit(1);
    }

    fwrite(STDOUT, "tags doc in sync: {$summary}\n");
    exit(0);
}

file_put_contents($page, $updated);
fwrite(STDOUT, "Wrote docs/tools/tags.md: {$summary}\n");
