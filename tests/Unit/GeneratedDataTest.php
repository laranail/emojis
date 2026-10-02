<?php

declare(strict_types=1);

/*
 * The generated shards are read by people as well as by PHP: a reviewer opening database/generated should
 * see 😂, not "\u{1F602}". Only invisible code points are escaped, because those are the ones that make a
 * diff look empty or that an editor can silently rewrite. This checks the bytes on disk independently of
 * the emitter that wrote them.
 */

/** @return list<string> */
function generatedShards(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/database/generated', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

it('writes no invisible character raw in any generated file', function (): void {
    $files = generatedShards();
    $invisible = '/(?![\n])[\p{Cc}\p{Cf}\p{Co}\p{Cs}\p{Zl}\p{Zp}]|(?! )\p{Zs}|[\x{FE00}-\x{FE0F}\x{E0100}-\x{E01EF}\x{E0000}-\x{E007F}\x{200C}\x{200D}\x{20E3}]/u';

    expect(count($files))->toBeGreaterThanOrEqual(10);

    foreach ($files as $file) {
        $found = preg_match($invisible, (string) file_get_contents($file), $match, PREG_OFFSET_CAPTURE);

        expect($found)->toBe(0, sprintf('%s has a raw U+%04X at byte %d', basename($file), $found === 1 ? mb_ord($match[0][0], 'UTF-8') : 0, $found === 1 ? $match[0][1] : 0));
    }
});

it('shows emoji as characters and escapes only what cannot be seen', function (): void {
    $emojis = (string) file_get_contents(dirname(__DIR__, 2) . '/database/generated/emojis.php');

    expect($emojis)->toContain("['😂', 'face with tears of joy'")
        ->and($emojis)->toContain('"👨\u{200D}👩\u{200D}👧"')
        ->and($emojis)->toContain('"❤\u{FE0F}"')
        ->and($emojis)->not->toContain('"\u{1F602}"');
});

it('shows symbols as characters', function (): void {
    $symbols = (string) file_get_contents(dirname(__DIR__, 2) . '/database/generated/symbols.php');

    expect($symbols)->toContain("=> ['→', 'rightwards arrow', 'Sm', 0, '&rarr;']")
        ->and($symbols)->toContain("'arrows'      => '← ↑ → ↓");
});

it('shows the newest emoji as characters too', function (): void {
    // Unicode 18.0 additions are unknown to the PCRE that ships with most PHP builds; the emitter must not
    // depend on PCRE knowing them, or two machines would write different bytes.
    expect((string) file_get_contents(dirname(__DIR__, 2) . '/database/generated/emojis.php'))->toContain("['🫫', 'cracking face'");
});
