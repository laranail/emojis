<?php

declare(strict_types=1);

/**
 * Builds database/generated/ — the shipped dataset — from the upstream sources pinned in
 * database/sources/upstream.lock.json (sha256-verified), the hand-maintained inputs in database/sources/curated/
 * and the image margins in database/sources/measured/. Also writes docs/licences.md, the notices the data's
 * licences require to travel with it.
 *
 *   php tools/build-dataset.php            verify sources, regenerate every shard
 *   php tools/build-dataset.php --check    regenerate in memory and byte-compare; exit 1 on drift
 *   php tools/build-dataset.php --fetch    download any missing source into build/cache/sources first
 *   php tools/build-dataset.php --lock     rewrite database/sources/upstream.lock.json from the cached files
 *                                          (maintainer action when bumping a source, never in CI)
 *
 * Sources are cached under build/cache/sources (gitignored). When they are absent and --fetch is not
 * given, --check reports SKIP and exits 0, mirroring atlas: a contributor without the sources can still
 * run the suite, and CI's hygiene job runs with --fetch so the check is never skipped where it matters.
 *
 * Every count this script asserts is read from the source itself (emoji-test.txt's "# Status Counts"
 * footer), never hard-coded, so a source bump cannot leave a stale floor passing.
 */

require __DIR__ . '/lib/PhpEmitter.php';

const ROOT = __DIR__ . '/..';
const CACHE = ROOT . '/build/cache/sources';
const LOCK = ROOT . '/database/sources/upstream.lock.json';
const DATA = ROOT . '/database/generated';
const OVERLAYS = ROOT . '/database/sources/curated';
const MEASURED = ROOT . '/database/sources/measured';
const LICENCES = ROOT . '/docs/licences.md';

const Q_FULLY = 0;
const Q_MINIMALLY = 1;
const Q_UNQUALIFIED = 2;
const Q_COMPONENT = 3;
const Q_TEXT_DEFAULT = 4;

const IMG_TWEMOJI = 1;
const IMG_NOTO = 2;
const IMG_OPENMOJI = 4;
const IMG_FLUENT = 8;

/** The locales shipped. en is always first; the rest are CLDR "modern" coverage locales chosen for reach. */
const LOCALES = ['en', 'ar', 'bn', 'de', 'es', 'fa', 'fr', 'hi', 'id', 'it', 'ja', 'ko', 'nl', 'pl', 'pt', 'ru', 'sv', 'sw', 'th', 'tr', 'uk', 'vi', 'zh', 'zh-Hant'];

/** Shortcode presets, in the priority order used to pick an emoji's primary shortcode. */
const PRESETS = ['github' => 'sc-github.json', 'emojibase' => 'sc-emojibase.json', 'slack' => 'sc-iamcal.json', 'joypixels' => 'sc-joypixels.json', 'cldr' => 'sc-cldr.json'];

$args = array_slice($argv, 1);
$check = in_array('--check', $args, true);
$fetch = in_array('--fetch', $args, true);

$fail = static function (string $message): never {
    fwrite(STDERR, "build-dataset: {$message}\n");

    exit(1);
};

/** @var array<string, array{url: string, path: string, sha256: string}> $lock */
$lock = json_decode((string) file_get_contents(LOCK), true, flags: JSON_THROW_ON_ERROR)['sources'];

if (in_array('--lock', $args, true)) {
    foreach ($lock as $id => $source) {
        $file = CACHE . '/' . $source['path'];
        is_file($file) || $fail("cannot lock {$id}: {$file} is missing");
        $lock[$id]['sha256'] = hash_file('sha256', $file);
    }

    $json = json_decode((string) file_get_contents(LOCK), true, flags: JSON_THROW_ON_ERROR);
    $json['sources'] = $lock;
    file_put_contents(LOCK, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, 'Locked ' . count($lock) . " sources.\n");

    exit(0);
}

foreach ($lock as $id => $source) {
    $file = CACHE . '/' . $source['path'];

    if (! is_file($file)) {
        if (! $fetch) {
            if ($check) {
                fwrite(STDOUT, "SKIP build-dataset --check: source {$id} not cached (run with --fetch).\n");

                exit(0);
            }

            $fail("source {$id} is not cached at {$file}; run with --fetch");
        }

        @mkdir(dirname($file), 0o775, true);
        exec(sprintf('curl -sSfL --retry 3 -o %s %s 2>&1', escapeshellarg($file), escapeshellarg($source['url'])), $out, $code);
        $code === 0 || $fail("download of {$id} failed: " . implode(' ', $out));
    }

    $actual = hash_file('sha256', $file);
    $actual === $source['sha256'] || $fail("sha256 mismatch for {$id}: expected {$source['sha256']}, got {$actual}");
}

$read = static fn (string $id): string => (string) file_get_contents(CACHE . '/' . $lock[$id]['path']);
$json = static fn (string $id): mixed => json_decode($read($id), true, flags: JSON_THROW_ON_ERROR);
$overlay = static fn (string $name): array => json_decode((string) file_get_contents(OVERLAYS . "/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);

// ---------------------------------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------------------------------

/** @return list<int> */
function cps(string $hex): array
{
    return array_map('hexdec', explode(' ', trim(preg_replace('/\s+/', ' ', str_replace('-', ' ', $hex)))));
}

/** @param list<int> $cps */
function hexOf(array $cps): string
{
    return implode('-', array_map(static fn (int $cp): string => sprintf('%04X', $cp), $cps));
}

/** @param list<int> $cps */
function charsOf(array $cps): string
{
    return implode('', array_map(static fn (int $cp): string => mb_chr($cp, 'UTF-8'), $cps));
}

function stripFe0f(string $hex): string
{
    return hexOf(array_values(array_filter(cps($hex), static fn (int $cp): bool => $cp !== 0xFE0F)));
}

function stripModifiers(string $hex): string
{
    return hexOf(array_values(array_filter(cps($hex), static fn (int $cp): bool => $cp < 0x1F3FB || $cp > 0x1F3FF)));
}

/** The name with its skin-tone clauses removed: "man: light skin tone, red hair" → "man: red hair". */
function toneBaseName(string $name): string
{
    $name = (string) preg_replace('/\b(?:light|medium-light|medium|medium-dark|dark) skin tone\b/', '', $name);
    $name = (string) preg_replace('/\s*,\s*(?=,|$)|(?<=:)\s*,\s*/', '', $name);
    $name = (string) preg_replace('/:\s*(?=\S)/', ': ', $name);

    return rtrim(trim($name), ':, ');
}

function slugify(string $name): string
{
    static $translit = null;
    $translit ??= Transliterator::create('Any-Latin; Latin-ASCII; Lower()');

    $name = str_replace(['#', '*', '&', '’', "'"], [' number sign ', ' asterisk ', ' and ', '', ''], $name);
    $ascii = $translit instanceof Transliterator ? (string) $translit->transliterate($name) : strtolower($name);

    return trim((string) preg_replace('/[^a-z0-9]+/', '_', $ascii), '_');
}

/** @param list<array{0:int,1:int}> $ranges */
function charClass(array $ranges): string
{
    usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
    $merged = [];

    foreach ($ranges as [$from, $to]) {
        $last = array_key_last($merged);

        if ($last !== null && $from <= $merged[$last][1] + 1) {
            $merged[$last][1] = max($merged[$last][1], $to);

            continue;
        }

        $merged[] = [$from, $to];
    }

    $out = '';

    foreach ($merged as [$from, $to]) {
        $out .= $from === $to ? sprintf('\x{%X}', $from) : sprintf('\x{%X}-\x{%X}', $from, $to);
    }

    return $out;
}

// ---------------------------------------------------------------------------------------------------
// 1. emoji-test.txt: the catalogue, its order, groups, qualification and English names
// ---------------------------------------------------------------------------------------------------

$test = $read('emoji-test');
preg_match('/^# Date: (\d{4}-\d{2}-\d{2})/m', $test, $dateMatch);
preg_match('/^# Version: (\d+\.\d+)/m', $read('emoji-data'), $versionMatch);
$unicodeDate = $dateMatch[1] ?? $fail('emoji-test.txt has no Date header');
$emojiVersion = $versionMatch[1] ?? $fail('emoji-data.txt has no Version header');

$expected = [];
preg_match_all('/^# (fully-qualified|minimally-qualified|unqualified|component) : (\d+)$/m', $test, $footer, PREG_SET_ORDER);

foreach ($footer as [, $status, $count]) {
    $expected[$status] = (int) $count;
}

count($expected) === 4 || $fail('emoji-test.txt status footer not found');

$groups = [];
$subgroups = [];
$groupIndex = -1;
$subgroupIndex = -1;
$records = [];
$aliases = [];
$seen = array_fill_keys(array_keys($expected), 0);
$order = 0;

foreach (explode("\n", $test) as $line) {
    if (preg_match('/^# group: (.+)$/', $line, $m) === 1) {
        $groups[++$groupIndex] = ['slug' => slugify($m[1]), 'name' => $m[1]];

        continue;
    }

    if (preg_match('/^# subgroup: (.+)$/', $line, $m) === 1) {
        $subgroups[++$subgroupIndex] = ['slug' => trim((string) preg_replace('/[^a-z0-9]+/', '-', str_replace('&', 'and', strtolower($m[1]))), '-'), 'name' => str_replace('-', ' ', $m[1]), 'group' => $groupIndex];

        continue;
    }

    if (preg_match('/^([0-9A-F ]+?)\s*;\s*(fully-qualified|minimally-qualified|unqualified|component)\s*#\s*\S+\s+E(\d+\.\d+)\s+(.+)$/u', $line, $m) !== 1) {
        continue;
    }

    [, $points, $status, $version, $name] = $m;
    $hex = hexOf(cps($points));
    $seen[$status]++;

    if ($status === 'minimally-qualified' || $status === 'unqualified') {
        $aliases[$hex] = $status;

        continue;
    }

    $records[$hex] = [
        'emoji'    => charsOf(cps($hex)),
        'name'     => $name,
        'group'    => $groupIndex,
        'subgroup' => $subgroupIndex,
        'version'  => $version,
        'status'   => $status,
        'order'    => $order++,
    ];
}

foreach ($expected as $status => $count) {
    $seen[$status] === $count || $fail("emoji-test.txt: footer says {$count} {$status}, parsed {$seen[$status]}");
}

// stripped (no FE0F) → fully-qualified hexcode, used to attach aliases and third-party hexcodes.
$byStripped = [];

foreach ($records as $hex => $record) {
    $hex = (string) $hex;
    $byStripped[stripFe0f($hex)] ??= $hex;
}

$resolve = static function (string $hex) use (&$records, &$byStripped): ?string {
    $hex = strtoupper(str_replace(' ', '-', $hex));

    return isset($records[$hex]) ? $hex : ($byStripped[stripFe0f($hex)] ?? null);
};

$byChar = [];
$byName = [];

foreach ($records as $hex => $record) {
    $hex = (string) $hex;
    $byChar[$record['emoji']] = $hex;
    $byName[$record['name']] = $hex;
}

// ---------------------------------------------------------------------------------------------------
// 2. emoji-data.txt and emoji-variation-sequences.txt: properties
// ---------------------------------------------------------------------------------------------------

$props = [];

foreach (explode("\n", $read('emoji-data')) as $line) {
    if (preg_match('/^([0-9A-F]+)(?:\.\.([0-9A-F]+))?\s*;\s*(\w+)/', $line, $m) === 1) {
        $props[$m[3]][] = [hexdec($m[1]), hexdec($m[2] !== '' ? $m[2] : $m[1])];
    }
}

isset($props['Extended_Pictographic'], $props['Emoji_Presentation'], $props['Emoji_Modifier_Base']) || $fail('emoji-data.txt is missing expected properties');

$textVs = [];

foreach (explode("\n", $read('emoji-variation-sequences')) as $line) {
    if (preg_match('/^([0-9A-F]+) FE0E\s*;/', $line, $m) === 1) {
        $textVs[hexdec($m[1])] = true;
    }
}

count($textVs) > 300 || $fail('emoji-variation-sequences.txt parsed ' . count($textVs) . ' text sequences');

$standardizedBases = [];

foreach (explode("\n", $read('standardized-variants')) as $line) {
    if (preg_match('/^([0-9A-F]+) (FE0[0-9A-D]);/', $line, $m) === 1) {
        $standardizedBases[] = [hexdec($m[1]), hexdec($m[1])];
    }
}

count($standardizedBases) > 1000 || $fail('StandardizedVariants.txt parsed only ' . count($standardizedBases) . ' sequences');

// ---------------------------------------------------------------------------------------------------
// 3. Per-record derived facts: type, base, tones, region, variation, slug
// ---------------------------------------------------------------------------------------------------

$slugs = [];

foreach ($records as $hex => &$record) {
    $hex = (string) $hex;
    $points = cps($hex);
    $noFe0f = array_values(array_filter($points, static fn (int $cp): bool => $cp !== 0xFE0F));
    $tones = array_values(array_map(static fn (int $cp): int => $cp - 0x1F3FA, array_filter($points, static fn (int $cp): bool => $cp >= 0x1F3FB && $cp <= 0x1F3FF)));

    $record['type'] = match (true) {
        in_array(0x200D, $points, true)                                        => 'zwj',
        in_array(0x20E3, $points, true)                                        => 'keycap',
        count($points) === 2 && $points[0] >= 0x1F1E6 && $points[0] <= 0x1F1FF => 'flag',
        in_array(0xE007F, $points, true)                                       => 'tag',
        $tones !== [] && count($noFe0f) === 2                                  => 'modifier',
        default                                                                => 'basic',
    };

    if ($record['type'] === 'flag') {
        $record['region'] = chr($points[0] - 0x1F1E6 + 65) . chr($points[1] - 0x1F1E6 + 65);
    } elseif ($record['type'] === 'tag') {
        $record['region'] = strtoupper(implode('', array_map(static fn (int $cp): string => chr($cp - 0xE0000), array_slice($points, 1, -1))));
    }

    if ($tones !== [] && $record['status'] === 'fully-qualified') {
        $record['tones'] = $tones;
        // The base is the emoji whose name is this one's without its tone clauses: "handshake: light skin
        // tone, dark skin tone" → "handshake". Stripping modifiers from the code points is not enough for
        // mixed-tone two-person sequences, whose tone-less form (🫱‍🫲) is not an emoji at all.
        // The gender-neutral couples are named "kiss: person, person, …" but their tone-less base is plain "kiss".
        $base = $byName[toneBaseName($record['name'])]
            ?? $byName[str_replace(': person, person', '', toneBaseName($record['name']))]
            ?? $resolve(stripModifiers($hex));

        if ($base !== null && $base !== $hex) {
            $record['base'] = $base;
        }
    }

    if (count($noFe0f) === 1 && isset($textVs[$noFe0f[0]])) {
        $record['text'] = true;
    }

    $gendered = array_intersect($points, [0x2640, 0x2642]);

    if ($gendered !== [] && $record['type'] === 'zwj') {
        $record['gender'] = in_array(0x2640, $gendered, true) ? 'female' : 'male';
    }

    foreach ([0x1F9B0 => 'red', 0x1F9B1 => 'curly', 0x1F9B2 => 'bald', 0x1F9B3 => 'white'] as $cp => $hair) {
        if (in_array($cp, $points, true) && $record['type'] === 'zwj') {
            $record['hair'] = $hair;
        }
    }

    if (in_array(0x27A1, $points, true) && $record['type'] === 'zwj') {
        $record['direction'] = 'right';
    }

    $slug = slugify($record['name']);
    $slug !== '' || $fail("empty slug for {$hex}");

    if (isset($slugs[$slug])) {
        $slug .= '_' . strtolower(str_replace('-', '_', $hex));
    }

    $slugs[$slug] = $hex;
    $record['slug'] = $slug;
}

unset($record);

// Skin-tone variant index on each base: "1" → hex, "1-5" → hex for two-person sequences.
foreach ($records as $hex => $record) {
    $hex = (string) $hex;
    if (isset($record['base'])) {
        $records[$record['base']]['skins'][implode('-', $record['tones'])] = $hex;
    }
}

// ---------------------------------------------------------------------------------------------------
// 4. Shortcodes: emojibase presets + gemoji + curated aliases
// ---------------------------------------------------------------------------------------------------

$presets = [];
$dropped = [];

$addCode = static function (string $preset, string $hex, string $code) use (&$presets, &$dropped): void {
    $code = strtolower(trim($code, ':'));

    if (preg_match('/^[a-z0-9_+\-]+$/', $code) !== 1) {
        $dropped[] = "{$preset}:{$code}";

        return;
    }

    if (! in_array($code, $presets[$preset][$hex] ?? [], true)) {
        $presets[$preset][$hex][] = $code;
    }
};

foreach (PRESETS as $preset => $file) {
    foreach ($json('emojibase-' . $preset) as $hex => $codes) {
        $hex = (string) $hex;
        $target = $resolve((string) $hex);

        if ($target === null) {
            continue;
        }

        foreach ((array) $codes as $code) {
            $addCode($preset, $target, (string) $code);
        }
    }
}

foreach ($json('gemoji') as $entry) {
    $target = $byChar[$entry['emoji']] ?? $resolve(hexOf(array_map(static fn (string $c): int => mb_ord($c, 'UTF-8'), mb_str_split($entry['emoji']))));

    foreach ($target === null ? [] : $entry['aliases'] as $alias) {
        $addCode('github', $target, $alias);
    }
}

$curatedAliases = $overlay('aliases')['aliases'];

foreach ($curatedAliases as $code => $hex) {
    isset($records[$hex]) || $fail("overlay aliases.json: {$code} points at unknown {$hex}");
}

// Reverse index: every code any preset (or the curated overlay) knows → one hexcode. Higher-priority
// presets win; each collision is recorded so the audit trail shows what lost.
$index = [];
$collisions = [];

foreach (array_keys(PRESETS) as $preset) {
    foreach ($presets[$preset] ?? [] as $hex => $codes) {
        $hex = (string) $hex;
        foreach ($codes as $code) {
            if (isset($index[$code]) && $index[$code] !== $hex) {
                $collisions[] = "{$code}: kept {$index[$code]}, {$preset} wanted {$hex}";

                continue;
            }

            $index[$code] ??= $hex;
        }
    }
}

foreach ($curatedAliases as $code => $hex) {
    $index[$code] ??= $hex;
}

// Every fully-qualified emoji's slug parses back to it, so Ascii output always round-trips.
foreach ($records as $hex => $record) {
    $hex = (string) $hex;
    if (! isset($index[$record['slug']])) {
        $index[$record['slug']] = $hex;
    } elseif ($index[$record['slug']] !== $hex) {
        $collisions[] = "{$record['slug']}: slug of {$hex} shadowed by {$index[$record['slug']]}";
    }
}

ksort($index, SORT_STRING);

// The ASCII token for every emoji: the highest-priority preset code, else its slug.
foreach ($records as $hex => &$record) {
    $hex = (string) $hex;
    $ascii = null;

    foreach (array_keys(PRESETS) as $preset) {
        foreach ($presets[$preset][$hex] ?? [] as $code) {
            if (($index[$code] ?? null) === $hex) {
                $ascii = $code;

                break 2;
            }
        }
    }

    $record['ascii'] = $ascii ?? $record['slug'];
}

unset($record);

// ---------------------------------------------------------------------------------------------------
// 5. Emoticons and kaomoji
// ---------------------------------------------------------------------------------------------------

$normaliseEmoticon = static fn (string $value): string => strtr(trim($value), ["\u{2011}" => '-', "\u{2010}" => '-', "\u{2019}" => "'", "\u{00B4}" => "'"]);
$emoticons = [];
$emoticonSource = [];

$addEmoticon = static function (string $value, ?string $hex, string $source) use (&$emoticons, &$emoticonSource, $normaliseEmoticon): void {
    $value = $normaliseEmoticon($value);

    if ($hex === null || $value === '' || preg_match('/^[\x21-\x7E]{2,}$/', $value) !== 1 || isset($emoticons[$value])) {
        return;
    }

    $emoticons[$value] = $hex;
    $emoticonSource[$value] = $source;
};

$curatedEmoticons = $overlay('emoticons');

foreach ($curatedEmoticons['emoticons'] as $value => $hex) {
    isset($records[$hex]) || $fail("overlay emoticons.json: {$value} points at unknown {$hex}");
    $addEmoticon($value, $hex, 'curated');
}

foreach ($json('emojibase-data') as $entry) {
    foreach ((array) ($entry['emoticon'] ?? []) as $value) {
        $addEmoticon((string) $value, $resolve($entry['hexcode']), 'emojibase');
    }
}

foreach ($json('googlefonts-ordering') as $group) {
    foreach ($group['emoji'] as $entry) {
        foreach ($entry['emoticons'] ?? [] as $value) {
            $addEmoticon((string) $value, $resolve(hexOf($entry['base'])), 'googlefonts');
        }
    }
}

foreach ($json('iamcal') as $entry) {
    foreach (array_merge((array) ($entry['texts'] ?? []), array_filter([(string) ($entry['text'] ?? '')])) as $value) {
        $addEmoticon((string) $value, $resolve($entry['unified']), 'iamcal');
    }
}

// "Risky" emoticons start with a letter or digit ("XD", "D:", "8)", "B-)"). They collide with
// ordinary prose and code, so they only match when a caller opts in.
$risky = [];

foreach (array_keys($emoticons) as $value) {
    if (preg_match('/^[A-Za-z0-9]/', $value) === 1 || in_array($value, $curatedEmoticons['risky'], true)) {
        $risky[$value] = true;
    }
}

$primaryEmoticon = [];

foreach ($emoticons as $value => $hex) {
    if (! isset($risky[$value])) {
        $primaryEmoticon[$hex] ??= $value;
    }
}

foreach ($curatedEmoticons['primary'] as $hex => $value) {
    $hex = (string) $hex;
    ($emoticons[$value] ?? null) === $hex || $fail("overlay emoticons.json primary {$value} does not map to {$hex}");
    $primaryEmoticon[$hex] = $value;
}

ksort($emoticons, SORT_STRING);
ksort($primaryEmoticon, SORT_STRING);
ksort($risky, SORT_STRING);

$kaomojiGroups = [];
$kaomoji = [];

foreach ($json('googlefonts-emoticons') as $group) {
    $slug = slugify($group['group']);
    $kaomojiGroups[$slug] = $group['group'];

    foreach ($group['emoticon'] as $item) {
        $value = $normaliseEmoticon((string) $item['value']);

        if ($value === '' || preg_match('/[\r\n\t]/', $value) === 1) {
            continue;
        }

        $kaomoji[$value] ??= ['value' => $value, 'group' => $slug, 'description' => strtolower(trim((string) $item['description'])), 'ascii' => preg_match('/^[\x20-\x7E]+$/', $value) === 1, 'tags' => '', 'reading' => ''];
    }
}

// Japanese kaomoji (顔文字) from kaomojikan/kaomoji-data (MIT): one-line faces only — its multi-line ASCII art
// cannot sit in running text. Tags and kana readings are kept, joined with " | ", for Japanese search. A face
// already in Google's set keeps Google's group and description.
$categories = $json('kaomojikan-categories');

foreach ($json('kaomojikan') as $item) {
    $value = trim((string) $item['text']);

    if ($value === '' || preg_match('/[\r\n\t]/', $value) === 1 || isset($kaomoji[$value])) {
        continue;
    }

    $group = 'ja_' . str_replace('-', '_', (string) ($item['categories'][0] ?? 'other'));
    $kaomojiGroups[$group] ??= (string) ($categories[$item['categories'][0] ?? '']['name'] ?? $group);
    $kaomoji[$value] = [
        'value'       => $value,
        'group'       => $group,
        'description' => (string) (($item['tags'] ?? [])[0] ?? ''),
        'ascii'       => preg_match('/^[\x20-\x7E]+$/', $value) === 1,
        'tags'        => implode(' | ', array_map('strval', $item['tags'] ?? [])),
        'reading'     => implode(' | ', array_map('strval', $item['reading'] ?? [])),
    ];
}

$kaomoji = array_values($kaomoji);
count($kaomoji) > 2000 || $fail('kaomoji: parsed only ' . count($kaomoji));

// ---------------------------------------------------------------------------------------------------
// 5b. Japanese carrier emoji: the docomo, au (KDDI) and SoftBank private-use code points, and Google's
//     plane-15 PUA. iamcal gives each emoji's code per carrier (many emoji share a docomo code). Which emoji a
//     shared code *reads as* comes from Unicode's EmojiSources.txt, the one-to-one carrier table (docomo's
//     U+E63E is the sun, not the sunrise that shares it); codes it does not list fall back to CLDR order.
// ---------------------------------------------------------------------------------------------------

$carriers = ['docomo' => [], 'au' => [], 'softbank' => [], 'google' => []];

foreach ($json('iamcal') as $entry) {
    $hex = $resolve($entry['unified']);

    foreach (array_keys($carriers) as $carrier) {
        $code = $entry[$carrier] ?? null;

        if ($hex === null || ! is_string($code) || preg_match('/^[0-9A-F]{4,5}$/', $code) !== 1) {
            continue;
        }

        $carriers[$carrier]['codes'][$hex] = $code;
        $existing = $carriers[$carrier]['reads'][$code] ?? null;

        if ($existing === null || $records[$hex]['order'] < $records[$existing]['order']) {
            $carriers[$carrier]['reads'][$code] = $hex;
        }
    }
}

// EmojiSources.txt columns: unicode ; docomo ; kddi ; softbank (Shift-JIS). A code listed there is canonical
// for its Unicode emoji, so that emoji wins the read for the carrier's private-use code point.
foreach (explode("\n", $read('emoji-sources')) as $line) {
    if ($line === '' || $line[0] === '#') {
        continue;
    }

    $fields = explode(';', $line);
    $hex = $resolve(hexOf(cps($fields[0])));

    foreach ([1 => 'docomo', 2 => 'au', 3 => 'softbank'] as $column => $carrier) {
        $code = $hex === null || trim($fields[$column] ?? '') === '' ? null : ($carriers[$carrier]['codes'][$hex] ?? null);

        if ($code !== null) {
            $carriers[$carrier]['reads'][$code] = $hex;
        }
    }
}

foreach ($carriers as $carrier => &$maps) {
    count($maps['codes'] ?? []) > 300 || $fail("carrier {$carrier}: only " . count($maps['codes'] ?? []) . ' mappings');
    ksort($maps['codes'], SORT_STRING);
    ksort($maps['reads'], SORT_STRING);
}

unset($maps);

// ---------------------------------------------------------------------------------------------------
// 5c. Curated collections (database/sources/curated/collections.json)
// ---------------------------------------------------------------------------------------------------

$collections = [];

foreach ($overlay('collections')['collections'] as $name => $members) {
    foreach ($members as $hex) {
        isset($records[$hex]) || $fail("overlay collections.json: {$name} lists unknown {$hex}");
    }

    count(array_unique($members)) === count($members) || $fail("overlay collections.json: {$name} lists an emoji twice");
    $collections[$name] = implode(' ', $members);
}

// ---------------------------------------------------------------------------------------------------
// 6. Image-set coverage
// ---------------------------------------------------------------------------------------------------

$twemojiFiles = [];

foreach ($json('twemoji-listing')['files'] as $file) {
    if (preg_match('#^/assets/svg/([0-9a-f-]+)\.svg$#', $file['name'], $m) === 1) {
        $twemojiFiles[$m[1]] = true;
    }
}

$notoFiles = [];

foreach ($json('noto-listing')['tree'] as $file) {
    if (preg_match('/^emoji_u([0-9a-f_]+)\.svg$/', $file['path'], $m) === 1) {
        $notoFiles[$m[1]] = true;
    }
}

$openmojiFiles = [];

foreach ($json('openmoji-data') as $entry) {
    $openmojiFiles[strtoupper($entry['hexcode'])] = true;
}

$fluentFolders = [];

foreach ($json('fluent-listing')['tree'] as $entry) {
    // Only folders whose file stem is provably lowercase-with-underscores (letters, digits, spaces, hyphens).
    if ($entry['type'] === 'tree' && preg_match('/^[A-Za-z0-9 \-]+$/', $entry['path']) === 1) {
        $fluentFolders[strtolower(preg_replace('/[^A-Za-z0-9]+/', ' ', $entry['path']))] = $entry['path'];
    }
}

count($twemojiFiles) > 3000 && count($notoFiles) > 3000 && count($openmojiFiles) > 3000 && count($fluentFolders) > 1000
    || $fail('image listings look truncated');

$fluent = [];

foreach ($records as $hex => &$record) {
    $hex = (string) $hex;
    $points = cps($hex);
    $lower = array_map(static fn (int $cp): string => sprintf('%x', $cp), $points);
    $twemoji = in_array('200d', $lower, true) ? implode('-', $lower) : implode('-', array_values(array_diff($lower, ['fe0f'])));
    $noto = implode('_', array_map(static fn (string $h): string => str_pad($h, 4, '0', STR_PAD_LEFT), array_values(array_diff($lower, ['fe0f']))));

    $bits = 0;
    $bits |= isset($twemojiFiles[$twemoji]) ? IMG_TWEMOJI : 0;
    $bits |= isset($notoFiles[$noto]) ? IMG_NOTO : 0;
    $bits |= isset($openmojiFiles[$hex]) || isset($openmojiFiles[stripFe0f($hex)]) ? IMG_OPENMOJI : 0;

    // Fluent is name-based and ships skin tones as sub-folders of the base emoji's folder.
    $fluentName = strtolower(preg_replace('/[^A-Za-z0-9]+/', ' ', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $records[$record['base'] ?? $hex]['name'])));
    $fluentName = trim($fluentName);

    // Fluent has tone folders only for single-person emoji, so a two-person base (handshake, couples) is
    // covered untoned and its tone variants are not covered at all.
    $twoPerson = str_contains(implode(' ', array_map('strval', array_keys($records[$record['base'] ?? $hex]['skins'] ?? []))), '-');

    if (isset($fluentFolders[$fluentName]) && (! isset($record['tones']) || (count($record['tones']) === 1 && ! $twoPerson))) {
        $bits |= IMG_FLUENT;
        $fluent[$hex] = $fluentFolders[$fluentName];
    }

    if ($bits !== 0) {
        $record['img'] = $bits;
    }
}

unset($record);

// ---------------------------------------------------------------------------------------------------
// 6b. Image crops, from the margins measured by tools/measure/measure-bounds.mjs (database/sources/measured/bounds).
//     Balanced removes the set's common padding: an inset of min(set median margin, this emoji's smallest
//     margin), so it never clips and a small symbol keeps its smallness. Tight is the square around the
//     artwork. Both in permille of the canvas; margins were rounded down, so neither cuts into a pixel.
// ---------------------------------------------------------------------------------------------------

$crops = [];
$imageVersions = ['twemoji' => 'twemoji-listing', 'noto' => 'noto-listing', 'openmoji' => 'openmoji-data', 'fluent' => 'fluent-listing'];

foreach ($imageVersions as $set => $lockId) {
    $file = MEASURED . "/bounds/{$set}.json";

    if (! is_file($file)) {
        continue;
    }

    $bounds = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    $bounds['version'] === $lock[$lockId]['version'] || $fail("bounds/{$set}.json measured {$bounds['version']}, but the lock pins {$lock[$lockId]['version']}: re-measure");
    count($bounds['entries']) > 2500 || $fail("bounds/{$set}.json has only " . count($bounds['entries']) . ' entries');

    $minima = array_map(static fn (array $m): int => min($m), $bounds['entries']);
    sort($minima);
    $safeArea = $minima[intdiv(count($minima), 2)];

    foreach ($bounds['entries'] as $hex => [$left, $top, $right, $bottom]) {
        $hex = (string) $hex;

        if (! isset($records[$hex])) {
            continue;
        }

        $inset = min($safeArea, $left, $top, $right, $bottom);
        $width = 1000 - $left - $right;
        $height = 1000 - $top - $bottom;
        $size = max($width, $height, 1);
        $x = max(0, min(1000 - $size, intdiv(2 * $left + $width - $size, 2)));
        $y = max(0, min(1000 - $size, intdiv(2 * $top + $height - $size, 2)));
        $crops[$set][$hex] = "{$inset} {$x} {$y} {$size}";
    }

    ksort($crops[$set], SORT_STRING);
}

// ---------------------------------------------------------------------------------------------------
// 7. Locales: CLDR names and keywords for base emoji and components
// ---------------------------------------------------------------------------------------------------

$locales = [];

foreach (LOCALES as $locale) {
    // Base annotations first; derived ones (flags, gendered and hair sequences) fill the gaps. Derived
    // skin-tone entries are skipped with the variants below, since tone names are composed at runtime.
    $annotations = $json('cldr-' . $locale)['annotations']['annotations'] + $json('cldr-derived-' . $locale)['annotationsDerived']['annotations'];
    $names = [];
    $keywords = [];

    foreach ($records as $hex => $record) {
        $hex = (string) $hex;
        if (isset($record['base'])) {
            continue; // composed at runtime from the base name and the tone names
        }

        $entry = $annotations[$record['emoji']] ?? $annotations[str_replace("\u{FE0F}", '', $record['emoji'])] ?? null;

        if ($entry === null) {
            continue;
        }

        $name = $entry['tts'][0] ?? null;

        if ($locale !== 'en' && is_string($name) && $name !== '') {
            $names[$hex] = $name;
        }

        $words = array_values(array_unique(array_filter(array_map('trim', $entry['default'] ?? []), static fn (string $w): bool => $w !== '' && $w !== $name)));

        if ($words !== []) {
            // One joined string per emoji rather than a list: a third of the tokens, and Pint's lint time over
            // the shards is proportional to tokens. Locales::keywords() splits it on read.
            array_filter($words, static fn (string $w): bool => str_contains($w, ' | ')) === [] || $fail("a {$locale} keyword contains the ' | ' separator");
            $keywords[$hex] = implode(' | ', $words);
        }
    }

    $locales[$locale] = ['locale' => $locale, 'names' => $names, 'keywords' => $keywords];
}

// ---------------------------------------------------------------------------------------------------
// 8. Scanner tables
// ---------------------------------------------------------------------------------------------------

$sequences = [];
$startRanges = [];

$addSequence = static function (string $hex, int $quality, string $target) use (&$sequences, &$startRanges): void {
    $points = cps($hex);
    $sequences[charsOf($points)] = [$target, $quality];
    $startRanges[] = [$points[0], $points[0]];
};

foreach ($records as $hex => $record) {
    $hex = (string) $hex;
    $addSequence($hex, $record['status'] === 'component' ? Q_COMPONENT : Q_FULLY, $hex);
}

foreach ($aliases as $hex => $status) {
    $hex = (string) $hex;
    $target = $resolve($hex) ?? $fail("alias {$hex} resolves to no fully-qualified emoji");
    $quality = $status === 'minimally-qualified' ? Q_MINIMALLY : (count(cps($hex)) === 1 ? Q_TEXT_DEFAULT : Q_UNQUALIFIED);
    $addSequence($hex, $quality, $target);
}

// Keycap bases without FE0F ("#⃣") are unqualified but not listed for every base; accept them explicitly.
foreach ($records as $hex => $record) {
    $hex = (string) $hex;
    if ($record['type'] === 'keycap') {
        $addSequence(stripFe0f($hex), Q_UNQUALIFIED, $hex);
    }
}

ksort($sequences, SORT_STRING);
$lengths = array_values(array_unique(array_map('strlen', array_keys($sequences))));
rsort($lengths);

$scanner = [
    'lengths'      => $lengths,
    'start'        => charClass($startRanges),
    'pictographic' => charClass(array_merge($props['Extended_Pictographic'], [[0x1F1E6, 0x1F1FF]])),
    // Which characters may legitimately take a variation selector: VS15/VS16 per emoji-variation-sequences.txt,
    // VS1–VS14 per StandardizedVariants.txt. Any other selector is payload (see Security\\Sanitizer).
    'emojiVsBases'        => charClass(array_map(static fn (int $cp): array => [$cp, $cp], array_keys($textVs))),
    'standardizedVsBases' => charClass($standardizedBases),
    'components'          => charClass([[0xFE0E, 0xFE0F], [0x20E3, 0x20E3], [0x1F3FB, 0x1F3FF], [0x1F9B0, 0x1F9B3], [0xE0020, 0xE007F]]),
    'sequences'           => $sequences,
];

// ---------------------------------------------------------------------------------------------------
// 9. Emit
// ---------------------------------------------------------------------------------------------------

$sourceLine = sprintf('Unicode Emoji %s (%s) · CLDR %s · emojibase-data %s · gemoji %s · googlefonts/emoji-metadata %s', $emojiVersion, $unicodeDate, $lock['cldr-en']['version'], $lock['emojibase-data']['version'], substr($lock['gemoji']['version'], 0, 12), substr($lock['googlefonts-ordering']['version'], 0, 12));
$header = static fn (string $what): string => "GENERATED by tools/build-dataset.php — do not edit.\n\n{$what}\n\nSources: {$sourceLine}\nLicences: docs/licences.md";

// One positional list per record, on one line. A map per record made this shard 50,000 lines that Pint
// needed two minutes to verify; the field order is written into the shard and checked against
// Simtabi\Laranail\Emojis\Core\Data\Record::FIELDS at load, so a mismatch fails loudly instead of shifting
// every field by one.
const FIELDS = ['emoji', 'name', 'slug', 'ascii', 'group', 'subgroup', 'version', 'type', 'component', 'base', 'tones', 'skins', 'text', 'region', 'gender', 'hair', 'direction', 'img'];

$emojis = [];

foreach ($records as $hex => $record) {
    $hex = (string) $hex;
    $skins = null;

    if (isset($record['skins'])) {
        $skins = implode(' ', array_map(static fn (string $key, string $target): string => "{$key}={$target}", array_map('strval', array_keys($record['skins'])), $record['skins']));
    }

    $emojis[$hex] = [
        $record['emoji'], $record['name'], $record['slug'], $record['ascii'], $record['group'], $record['subgroup'],
        $record['version'], $record['type'], $record['status'] === 'component', $record['base'] ?? null,
        isset($record['tones']) ? implode('-', $record['tones']) : null, $skins, $record['text'] ?? false,
        $record['region'] ?? null, $record['gender'] ?? null, $record['hair'] ?? null, $record['direction'] ?? null,
        $record['img'] ?? 0,
    ];
}

$shortcodes = [];

foreach (array_keys(PRESETS) as $preset) {
    $shortcodes[$preset] = [];

    foreach ($records as $hex => $record) {
        $hex = (string) $hex;

        if (isset($presets[$preset][$hex])) {
            // Space-joined (codes never contain a space), for the same token-count reason as keywords.
            $shortcodes[$preset][$hex] = implode(' ', $presets[$preset][$hex]);
        }
    }
}

$files = [
    'emojis.php'      => PhpEmitter::file(['fields' => FIELDS, 'groups' => $groups, 'subgroups' => $subgroups, 'emojis' => $emojis], $header("The catalogue: every fully-qualified emoji and component, keyed by hexcode, in CLDR order.\nEach record is a list in the order given by 'fields'. tones: \"3\" or \"1-5\"; skins: \"1=HEX 1-2=HEX …\".")),
    'scanner.php'     => PhpEmitter::file($scanner, $header("Scanner tables: every emoji sequence (fully-, minimally-, unqualified, component) → [hexcode, quality],\nthe byte lengths to probe longest-first, and the generated character classes. 'components' holds the\ncharacters that are meaningless outside an emoji sequence (VS15/16, keycap, modifiers, hair, tags);\nZWJ is deliberately absent because Indic and Persian scripts use it in ordinary words. No PCRE Unicode\nproperty is used at runtime; these classes are the portable replacement.")),
    'shortcodes.php'  => PhpEmitter::file(['presets' => $shortcodes, 'index' => $index], $header('Shortcodes per preset (hexcode → codes, primary first) and the merged reverse index (code → hexcode).')),
    'emoticons.php'   => PhpEmitter::file(['map' => $emoticons, 'risky' => array_keys($risky), 'primary' => $primaryEmoticon], $header('ASCII emoticons → hexcode, the opt-in "risky" subset, and each emoji\'s primary emoticon.')),
    'kaomoji.php'     => PhpEmitter::file(['groups' => $kaomojiGroups, 'items' => $kaomoji], $header("Kaomoji and text faces, grouped: googlefonts/emoji-metadata emoticon_ordering.json, then kaomojikan/kaomoji-data\n(groups prefixed ja_, with Japanese tags and kana readings).")),
    'collections.php' => PhpEmitter::file($collections, $header('Curated named collections: name => space-joined hexcodes, in display order.')),
    'carriers.php'    => PhpEmitter::file($carriers, $header("Japanese carrier emoji: per carrier, 'codes' (hexcode -> private-use code point) and 'reads'\n(code point -> hexcode; shared codes read as the first emoji in CLDR order).")),
    'images.php'      => PhpEmitter::file(['bits' => ['twemoji' => IMG_TWEMOJI, 'noto' => IMG_NOTO, 'openmoji' => IMG_OPENMOJI, 'fluent' => IMG_FLUENT], 'versions' => ['twemoji' => $lock['twemoji-listing']['version'], 'noto' => $lock['noto-listing']['version'], 'openmoji' => $lock['openmoji-data']['version'], 'fluent' => $lock['fluent-listing']['version']], 'fluent' => $fluent, 'crops' => $crops], $header('Image-set coverage bits, pinned CDN versions, Fluent folder names, and measured crops ("inset x y size", permille).')),
];

foreach ($locales as $locale => $data) {
    $files["locales/{$locale}.php"] = PhpEmitter::file($data, $header("CLDR annotations for {$locale}: names (non-en only; en uses emoji-test names) and keywords."));
}

$files['VERSION'] = $sourceLine . "\n";

// The licences travel with the data they cover (Unicode License V3 requires it; Apache-2.0 and MIT too).
$notice = "# Licences\n\nThe notices for the third-party data in `database/generated/`, which the data's licences require to travel with it.\n\n"
    . "The dataset is generated by `tools/build-dataset.php` from the sources below and redistributed under their\nlicences. The package's own code is MIT (see `LICENSE`). This page ships inside the Composer archive for that\nreason, unlike the rest of `docs/`.\n\n## Sources\n\n"
    . "| Source | Version | Licence | Used for |\n|---|---|---|---|\n"
    . "| Unicode emoji data files | {$emojiVersion} | Unicode License V3 | catalogue, order, groups, names, properties |\n"
    . "| Unicode CLDR annotations | {$lock['cldr-en']['version']} | Unicode License V3 | localized names and keywords |\n"
    . "| emojibase-data | {$lock['emojibase-data']['version']} | MIT | shortcode presets, emoticons |\n"
    . "| github/gemoji | {$lock['gemoji']['version']} | MIT | GitHub shortcodes |\n"
    . "| googlefonts/emoji-metadata | {$lock['googlefonts-ordering']['version']} | Apache License 2.0 | emoticons, kaomoji |\n"
    . "| iamcal/emoji-data | {$lock['iamcal']['version']} | MIT | emoticons, Japanese carrier codes |\n"
    . "| kaomojikan/kaomoji-data | {$lock['kaomojikan']['version']} | MIT | Japanese kaomoji, tags, readings |\n"
    . "| Unicode EmojiSources.txt | {$lock['emoji-sources']['version']} | Unicode License V3 | canonical carrier mappings |\n\n"
    . "Image sets are never bundled; image URLs point at the publisher's CDN and carry the publisher's licence\n(see [images](tools/images.md#attribution)).\n";

foreach (['unicode-license' => 'Unicode License V3 (Unicode data files and CLDR)', 'emojibase-license' => 'emojibase-data (MIT)', 'gemoji-license' => 'github/gemoji (MIT)', 'googlefonts-license' => 'googlefonts/emoji-metadata (Apache License 2.0)', 'iamcal-license' => 'iamcal/emoji-data (MIT)', 'kaomojikan-license' => 'kaomojikan/kaomoji-data (MIT)'] as $id => $title) {
    $notice .= "\n## {$title}\n\n```text\n" . rtrim(str_replace("\r\n", "\n", $read($id))) . "\n```\n";
}

$files[LICENCES] = $notice . "\n---\n\n[← Docs index](../README.md#documentation)\n";

$drift = [];

foreach ($files as $name => $contents) {
    $path = str_starts_with($name, '/') ? $name : DATA . '/' . $name;
    $name = str_starts_with($name, '/') ? substr($name, strlen(ROOT) + 1) : $name;

    if ($check) {
        if (! is_file($path) || file_get_contents($path) !== $contents) {
            $drift[] = $name;
        }

        continue;
    }

    @mkdir(dirname($path), 0o775, true);
    file_put_contents($path, $contents);
}

if ($check) {
    $drift === [] || $fail('generated data is stale: ' . implode(', ', $drift) . ' (run composer build-dataset)');
    fwrite(STDOUT, 'build-dataset --check: ' . count($files) . " files in sync.\n");

    exit(0);
}

// The collision and drop report is an audit artefact, written outside the repo's shipped files.
@mkdir(ROOT . '/build', 0o775, true);
file_put_contents(ROOT . '/build/dataset-report.txt', implode("\n", [
    'records: ' . count($records) . ' (' . $expected['fully-qualified'] . ' fully-qualified + ' . $expected['component'] . ' component)',
    'aliases: ' . count($aliases),
    'sequences: ' . count($sequences) . ', lengths: ' . implode(',', $lengths),
    'shortcodes indexed: ' . count($index) . ', dropped (non-ASCII / illegal): ' . count($dropped),
    'emoticons: ' . count($emoticons) . ' (' . count($risky) . ' risky), primaries: ' . count($primaryEmoticon),
    'kaomoji: ' . count($kaomoji),
    'fluent mapped: ' . count($fluent),
    '',
    'collisions:',
    ...$collisions,
    '',
    'dropped:',
    ...$dropped,
]) . "\n");

fwrite(STDOUT, 'Wrote ' . count($files) . ' files for ' . count($records) . " emoji. Report: build/dataset-report.txt\n");
