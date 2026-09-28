<?php

declare(strict_types=1);

/**
 * Brings the dataset up to the newest upstream releases. Run weekly by .github/workflows/refresh.yml, which
 * turns the result into a pull request; never pushed to main, never merged without review.
 *
 *   php .dev/tools/refresh.php              find newer versions, download, re-lock, rebuild, cross-check
 *   php .dev/tools/refresh.php --dry-run    only report which sources have newer versions
 *
 * For every source family it asks the family's own registry for the latest version (Unicode's latest/
 * directory, the npm registry, a GitHub release or the tip of a branch), rewrites the pinned URLs and
 * versions in database/sources/upstream.lock.json, downloads the new files, and re-locks their SHA-256.
 * A file whose pinned version did not change must still hash to its locked value — if it does not, the
 * upstream changed content under an unchanged name and the refresh stops rather than pinning it.
 *
 * When an image set moves, every image of it is downloaded (hash-checked by content, not trusted), its
 * margins are re-measured (.dev/tools/measure, needs Node) and its hashes recorded. Then the dataset and enums
 * are regenerated and .dev/tools/cross-check.php compares the catalogue with the published lists.
 *
 * Network access is limited to the hosts in HOSTS, over https, without redirects. A summary for the pull
 * request body is written to build/refresh-report.md.
 */

use Simtabi\Laranail\Emojis\Core\Image\HttpsFetcher;

const ROOT = __DIR__ . '/../..';
const LOCK = ROOT . '/database/sources/upstream.lock.json';
const CACHE = ROOT . '/build/cache/sources';
const REPORT = ROOT . '/build/refresh-report.md';
const HOSTS = ['www.unicode.org', 'registry.npmjs.org', 'api.github.com', 'cdn.jsdelivr.net', 'data.jsdelivr.com', 'raw.githubusercontent.com', 'html.spec.whatwg.org'];

/**
 * Each family moves as one: every lock entry whose id is listed (or starts with the prefix) carries the
 * family's version in its URL. `images` names the image set whose files and measurements follow it.
 */
const FAMILIES = [
    'unicode'     => ['ids' => ['emoji-test', 'emoji-data', 'emoji-variation-sequences', 'emoji-sources', 'standardized-variants', 'unicode-data', 'unicode-blocks'], 'latest' => ['unicode']],
    'cldr'        => ['prefix' => 'cldr-', 'latest' => ['npm', 'cldr-annotations-full']],
    'emojibase'   => ['prefix' => 'emojibase-', 'latest' => ['npm', 'emojibase-data']],
    'iamcal'      => ['ids' => ['iamcal', 'iamcal-license'], 'latest' => ['npm', 'emoji-datasource']],
    'openmoji'    => ['ids' => ['openmoji-data'], 'latest' => ['npm', 'openmoji'], 'images' => 'openmoji'],
    'twemoji'     => ['ids' => ['twemoji-listing'], 'latest' => ['release', 'jdecked/twemoji'], 'images' => 'twemoji'],
    'gemoji'      => ['ids' => ['gemoji', 'gemoji-license'], 'latest' => ['commit', 'github/gemoji', 'master']],
    'googlefonts' => ['prefix' => 'googlefonts-', 'latest' => ['commit', 'googlefonts/emoji-metadata', 'main']],
    'kaomojikan'  => ['prefix' => 'kaomojikan', 'latest' => ['commit', 'kaomojikan/kaomoji-data', 'main']],
    'noto'        => ['ids' => ['noto-listing'], 'latest' => ['commit', 'googlefonts/noto-emoji', 'main'], 'images' => 'noto'],
    'fluent'      => ['ids' => ['fluent-listing'], 'latest' => ['commit', 'microsoft/fluentui-emoji', 'main'], 'images' => 'fluent'],
];

require ROOT . '/vendor/autoload.php';

$dryRun = in_array('--dry-run', $argv, true);
$php = escapeshellarg(PHP_BINARY);

$fail = static function (string $message): never {
    fwrite(STDERR, "refresh: {$message}\n");

    exit(1);
};

/**
 * GET one URL from an allowed host; GitHub API calls carry GITHUB_TOKEN when set (rate limits).
 *
 * Source files are requested exactly as build-dataset.php's curl requests them — no Accept header — because
 * the bytes are what gets pinned: GitHub's API answers `Accept: application/json` with minified JSON and the
 * default with pretty-printed JSON, the same data under a different SHA-256. Only version lookups ($json)
 * ask for JSON.
 */
$get = static function (string $url, bool $json = false) use ($fail): string {
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    in_array($host, HOSTS, true) && str_starts_with($url, 'https://') || $fail("refusing to fetch from {$host}");

    $headers = ['User-Agent: laranail-emojis-refresh', 'Accept: ' . ($json ? 'application/json' : '*/*')];
    $token = getenv('GITHUB_TOKEN');

    if ($host === 'api.github.com' && is_string($token) && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS_STR  => 'https',
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if (is_string($body) && $status === 200) {
            return $body;
        }

        usleep(500_000 * $attempt);
    }

    $fail("GET {$url} failed");
};

/** @return array<string, mixed> */
$getJson = static fn (string $url): array => (array) json_decode($get($url, json: true), true, flags: JSON_THROW_ON_ERROR);

$latest = static function (array $how) use ($get, $getJson): string {
    return match ($how[0]) {
        'unicode' => preg_match('/^# Version: (\d+\.\d+)/m', $get('https://www.unicode.org/Public/emoji/latest/emoji-test.txt'), $m) === 1 ? $m[1] : '',
        'npm'     => (string) ($getJson("https://registry.npmjs.org/{$how[1]}/latest")['version'] ?? ''),
        'release' => ltrim((string) ($getJson("https://api.github.com/repos/{$how[1]}/releases/latest")['tag_name'] ?? ''), 'v'),
        'commit'  => (string) ($getJson("https://api.github.com/repos/{$how[1]}/commits/{$how[2]}")['sha'] ?? ''),
    };
};

$json = json_decode((string) file_get_contents(LOCK), true, flags: JSON_THROW_ON_ERROR);
$lock = $json['sources'];
$changes = [];

foreach (FAMILIES as $family => $spec) {
    $ids = array_values(array_filter(array_keys($lock), static fn (string $id): bool => in_array($id, $spec['ids'] ?? [], true) || (isset($spec['prefix']) && str_starts_with($id, $spec['prefix']))));
    $ids !== [] || $fail("family {$family} matches no lock entry");
    $current = $lock[$ids[0]]['version'];
    $new = $latest($spec['latest']);
    preg_match('/^[0-9a-f]{40}$|^\d+(\.\d+){1,2}$/', $new) === 1 || $fail("family {$family}: implausible latest version \"{$new}\"");

    if ($new === $current) {
        continue;
    }

    $changes[$family] = [$current, $new, $ids, $spec['images'] ?? null];
}

$report = ["## Upstream refresh\n"];

foreach ($changes as $family => [$old, $new, $ids]) {
    $report[] = sprintf('- **%s**: `%s` → `%s` (%d files)', $family, substr($old, 0, 12), substr($new, 0, 12), count($ids));
}

if ($changes === []) {
    $report[] = '- every source is at its latest version';
    fwrite(STDOUT, "refresh: every source is at its latest version.\n");
}

if ($dryRun) {
    fwrite(STDOUT, implode("\n", $report) . "\n");

    exit(0);
}

foreach ($changes as $family => [$old, $new, $ids]) {
    foreach ($ids as $id) {
        $unicodeSegment = static fn (string $v): string => '/' . $v . '.0/';
        $lock[$id]['url'] = $family === 'unicode'
            ? str_replace($unicodeSegment($old), $unicodeSegment($new), $lock[$id]['url'])
            : str_replace($old, $new, $lock[$id]['url']);
        $lock[$id]['version'] = $new;
        @unlink(CACHE . '/' . $lock[$id]['path']);
    }

    // The ordering file is named for the emoji version: emoji_18_0_ordering.json.
    if ($family === 'unicode') {
        $lock['googlefonts-ordering']['url'] = str_replace('emoji_' . str_replace('.', '_', $old) . '_ordering', 'emoji_' . str_replace('.', '_', $new) . '_ordering', $lock['googlefonts-ordering']['url']);
    }
}

// Files whose version is unchanged must still match their lock: content changing under a pinned name is
// exactly what pinning exists to catch. The WHATWG entity list has no versions, so a change there is news,
// not an attack — it is re-downloaded, re-locked and reported.
@unlink(CACHE . '/' . $lock['whatwg-entities']['path']);

foreach ($lock as $id => $source) {
    $file = CACHE . '/' . $source['path'];

    if (! is_file($file)) {
        @mkdir(dirname($file), 0o775, true);
        file_put_contents($file, $get($source['url']));
    }

    $unchanged = ! in_array($id, array_merge(...array_column($changes, 2)), true) && $id !== 'whatwg-entities';

    if ($unchanged && $source['sha256'] !== '' && hash_file('sha256', $file) !== $source['sha256']) {
        $fail("{$id} changed content without a version change ({$source['url']}); not pinning it");
    }

    $sha = hash_file('sha256', $file);

    if ($id === 'whatwg-entities' && $sha !== $source['sha256']) {
        $report[] = '- **whatwg-entities**: the entity list changed';
    }

    $lock[$id]['sha256'] = $sha;
}

$json['sources'] = $lock;
file_put_contents(LOCK, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$run = static function (string $command) use ($fail): void {
    passthru($command, $status);
    $status === 0 || $fail("step failed: {$command}");
};

$run("{$php} " . escapeshellarg(ROOT . '/.dev/tools/build-dataset.php'));

// An image set that moved: fetch every file (checked below by measuring and hashing what arrived), then
// re-measure margins and re-record hashes, and rebuild so crops and hashes match the new version.
$sets = array_values(array_filter(array_column($changes, 3)));

if ($sets !== []) {
    $run("{$php} " . escapeshellarg(ROOT . '/.dev/tools/measure/image-urls.php') . ' ' . implode(' ', array_map(escapeshellarg(...), $sets)));
    $fetcher = new HttpsFetcher(['cdn.jsdelivr.net']);

    foreach ($sets as $set) {
        $dir = ROOT . "/build/cache/images/{$set}";
        @mkdir($dir, 0o775, true);
        array_map(unlink(...), glob($dir . '/*.svg') ?: []);
        $jobs = [];

        foreach (file(ROOT . "/build/cache/images/{$set}.urls", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$hex, $url] = explode("\t", $line);
            $jobs[$url] = $hex;
        }

        foreach (array_chunk(array_keys($jobs), 16) as $chunk) {
            foreach ($fetcher($chunk) as $url => $body) {
                $body !== null && file_put_contents("{$dir}/{$jobs[$url]}.svg", $body);
            }
        }

        $version = $lock[array_values(array_filter(FAMILIES, static fn (array $f): bool => ($f['images'] ?? null) === $set))[0]['ids'][0]]['version'];
        $run('node ' . escapeshellarg(ROOT . '/.dev/tools/measure/measure-bounds.mjs') . ' ' . implode(' ', array_map(escapeshellarg(...), [$set, ROOT . "/build/cache/images/{$set}.urls", $dir, $version])));
        $run("{$php} " . escapeshellarg(ROOT . '/.dev/tools/measure/hash-images.php') . ' ' . implode(' ', array_map(escapeshellarg(...), [$set, $dir, $version])));
        $report[] = "- **{$set}** images re-downloaded, re-measured and re-hashed";
    }

    $run("{$php} " . escapeshellarg(ROOT . '/.dev/tools/build-dataset.php'));
}

$run("{$php} " . escapeshellarg(ROOT . '/.dev/tools/generate-enums.php'));

exec("{$php} " . escapeshellarg(ROOT . '/.dev/tools/cross-check.php') . ' 2>&1', $crossCheck, $crossStatus);
$report[] = "\n## Cross-check\n\n" . implode("\n", $crossCheck);

@mkdir(dirname(REPORT), 0o775, true);
file_put_contents(REPORT, implode("\n", $report) . "\n");
fwrite(STDOUT, implode("\n", $report) . "\n");

exit($crossStatus === 0 ? 0 : 1);
