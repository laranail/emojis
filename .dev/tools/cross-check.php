<?php

declare(strict_types=1);

/**
 * Compares the generated catalogue with lists published elsewhere, and prints a Markdown report.
 *
 *   php .dev/tools/cross-check.php            exit 1 when an emoji in Unicode's own charts is missing
 *   php .dev/tools/cross-check.php --strict   also exit 1 on a getemoji.com or copychar.cc gap
 *
 * - unicode.org full-emoji-list and full-emoji-modifiers: every emoji the Unicode Consortium charts must
 *   resolve, fully qualified. This is authoritative, so a miss fails.
 * - getemoji.com: everything it offers for copying must be recognised: an emoji in any qualification, a
 *   text-presentation form of one, or a symbol from our catalogue.
 * - copychar.cc: every character on every page its sitemap lists must be an emoji, a symbol in our catalogue, or one we
 *   exclude on purpose (controls, format and invisible characters, private use, combining marks).
 *
 * Only codes and characters are read; no image, description or other content is copied from these sites
 * (their images are not licensed for redistribution — see docs/tools/data-sources.md). Requests are few,
 * sequential and identified.
 */

use Simtabi\Laranail\Emojis\Core\Emojis;

const ROOT = __DIR__ . '/../..';
const HOSTS = ['www.unicode.org', 'getemoji.com', 'copychar.cc'];
const EXCLUDED = ['Cc', 'Cf', 'Co', 'Cs', 'Cn', 'Zs', 'Zl', 'Zp', 'Mn', 'Mc', 'Me'];
const COPYCHAR = ['popular', 'arrows', 'currency', 'emoji', 'hieroglyphs', 'letters', 'math', 'numbers', 'punctuation', 'symbols'];

require ROOT . '/vendor/autoload.php';

$strict = in_array('--strict', $argv, true);

$get = static function (string $url): ?string {
    in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), HOSTS, true) || throw new RuntimeException("host not allowed: {$url}");

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS_STR => 'https', CURLOPT_TIMEOUT => 120, CURLOPT_USERAGENT => 'laranail-emojis-cross-check (+https://github.com/laranail/emojis)']);
        $body = curl_exec($curl);

        if (is_string($body) && curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 200) {
            return $body;
        }

        sleep($attempt);
    }

    return null;
};

$emojis = Emojis::create();
$lines = [];
$failed = false;

// Unicode's charts: codes like "U+1F468 U+200D U+1F4BB" per row.
$unicodeMissing = [];
$unicodeCount = 0;

foreach (['full-emoji-list', 'full-emoji-modifiers'] as $chart) {
    $html = $get("https://www.unicode.org/emoji/charts/{$chart}.html") ?? throw new RuntimeException("could not fetch {$chart}");
    preg_match_all("~<td class='code'><a [^>]*>([^<]+)</a></td>~", $html, $m);

    foreach ($m[1] as $codes) {
        $unicodeCount++;
        $hex = strtoupper(str_replace(['U+', ' '], ['', '-'], trim($codes)));

        if ($emojis->fromHexcode($hex) === null) {
            $unicodeMissing[] = $hex;
        }
    }
}

$unicodeCount > 3000 || throw new RuntimeException("parsed only {$unicodeCount} rows from the Unicode charts; the markup changed");
$lines[] = sprintf('- Unicode charts: %d emoji, %d missing%s', $unicodeCount, count($unicodeMissing), $unicodeMissing === [] ? '' : ' — ' . implode(', ', array_slice($unicodeMissing, 0, 20)));
$failed = $failed || $unicodeMissing !== [];

// getemoji.com: one element per emoji.
$html = $get('https://getemoji.com/');

if ($html === null) {
    $lines[] = '- getemoji.com: not reachable, skipped';
} else {
    preg_match_all('~<div class="emoji emoji-button">([^<]+)</div>~u', $html, $m);
    $offered = array_unique(array_map(html_entity_decode(...), $m[1]));
    // Its symbols section offers plain characters (★ ✢) and text-presentation forms (✡︎ = U+2721 FE0E),
    // which are ours as symbols, or emoji asked to render as text.
    $known = static function (string $item) use ($emojis): bool {
        $bare = str_replace(["\u{FE0E}", "\u{FE0F}"], '', trim($item));

        return $emojis->find(trim($item)) !== null || $emojis->find($bare) !== null || $emojis->symbols()->get($bare) !== null;
    };
    $missing = array_values(array_filter($offered, static fn (string $e): bool => ! $known($e)));
    count($offered) > 1000 || throw new RuntimeException('parsed only ' . count($offered) . ' emoji from getemoji.com; the markup changed');
    $lines[] = sprintf('- getemoji.com: %d emoji, %d not recognised%s', count($offered), count($missing), $missing === [] ? '' : ' — ' . implode(' ', array_slice($missing, 0, 20)));
    $failed = $failed || ($strict && $missing !== []);
}

// copychar.cc: U+ codes on each page.
$categories = [];

foreach (file(ROOT . '/build/cache/sources/ucd/UnicodeData.txt', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $f = explode(';', $line);
    $categories[hexdec($f[0])] = $f[2] ?? '';
}

$seen = [];
$gaps = [];
$excluded = 0;
$unreachable = [];

// The pages come from the site's sitemap, so a page copychar.cc adds is checked rather than missed.
// COPYCHAR is what the site was measured to have (2026-10-02: those ten, plus the home page, which
// repeats "popular", and "about", which lists no characters); a difference is reported either way.
$sitemap = $get('https://copychar.cc/sitemap.xml');
$pages = COPYCHAR;

if ($sitemap === null) {
    $lines[] = '- copychar.cc/sitemap.xml: not reachable, checked the known pages only';
    $unreachable[] = 'sitemap.xml';
} else {
    preg_match_all('#<loc>https://copychar\.cc/([a-z0-9-]*)/?</loc>#', $sitemap, $locs);
    $listed = array_values(array_diff(array_unique($locs[1]), ['', 'about']));
    $added = array_diff($listed, COPYCHAR);
    $removed = array_diff(COPYCHAR, $listed);

    if ($added !== [] || $removed !== []) {
        $lines[] = '- copychar.cc pages changed — new: ' . (implode(', ', $added) ?: 'none') . '; gone: ' . (implode(', ', $removed) ?: 'none') . ' (update COPYCHAR)';
        $failed = $failed || ($strict && $added !== []);
    }

    $pages = array_values(array_unique([...$listed, ...COPYCHAR]));
}

foreach ($pages as $page) {
    $html = $get("https://copychar.cc/{$page}/");

    if ($html === null) {
        $lines[] = "- copychar.cc/{$page}: not reachable, skipped";
        $unreachable[] = $page;

        continue;
    }

    preg_match_all('/U\+([0-9A-F]{4,6})/', $html, $m);

    foreach (array_unique($m[1]) as $code) {
        $cp = (int) hexdec($code);

        if (isset($seen[$cp])) {
            continue;
        }

        $seen[$cp] = true;
        $char = mb_chr($cp, 'UTF-8');

        if ($emojis->symbols()->get($char) !== null || $emojis->find($char) !== null || $emojis->find($char . "\u{FE0F}") !== null) {
            continue;
        }

        in_array($categories[$cp] ?? 'Cn', EXCLUDED, true) ? $excluded++ : $gaps[] = sprintf('U+%04X', $cp);
    }

    sleep(1);
}

$lines[] = sprintf('- copychar.cc: %d pages, %d characters, %d excluded on purpose, %d not covered%s', count($pages) - count($unreachable), count($seen), $excluded, count($gaps), $gaps === [] ? '' : ' — ' . implode(' ', array_slice($gaps, 0, 30)));

// A page that did not load was not checked, so "0 not covered" would be claimed over characters nobody
// looked at. In strict mode that is a failure, not a skip.
$failed = $failed || ($strict && ($gaps !== [] || $unreachable !== [] || $seen === []));

fwrite(STDOUT, implode("\n", $lines) . "\n");

exit($failed ? 1 : 0);
