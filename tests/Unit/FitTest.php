<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Enums\Fit;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;

it('leaves full-bleed artwork as a plain <img>', function (): void {
    // Twemoji faces touch the canvas edge: there is nothing to crop, so no wrapper is added.
    expect(emojis()->text('😀')->fit(Fit::Balanced)->toImages())->toStartWith('<img ')
        ->and(emojis()->text('😀')->fit(Fit::Tight)->toImages())->toStartWith('<img ');
});

it('crops a small symbol to its artwork with Fit::Tight, as an svg viewBox', function (): void {
    $html = emojis()->text('🔹')->fit(Fit::Tight)->toImages();

    expect($html)->toStartWith('<svg class="emoji" viewBox="205 205 590 590"')
        ->and($html)->toContain('role="img" aria-label="small blue diamond"')
        ->and($html)->toContain('<title>small blue diamond</title>')
        ->and($html)->toContain('<image href="https://cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.3/assets/svg/1f539.svg" width="1000" height="1000"')
        ->and($html)->toContain('data-laranail-fit="tight"')
        ->and($html)->not->toContain('style=');
});

it('never renders fitted markup for Fit::None', function (): void {
    expect(emojis()->text('🔹')->fit(Fit::None)->toImages())->toStartWith('<img ');
});

it('reads its own fitted svg back', function (): void {
    $html = emojis()->text('go 🔹 now')->fit(Fit::Tight)->toImages();

    expect(emojis()->text($html)->from(Mode::Image)->toEmoji())->toBe('go 🔹 now');
});

it('keeps every crop inside the canvas and never inside the artwork, in every measured set', function (string $set): void {
    $bounds = json_decode((string) file_get_contents(dirname(__DIR__, 2) . "/database/sources/measured/bounds/{$set}.json"), true);
    $data = require dirname(__DIR__, 2) . '/database/generated/images.php';
    $checked = 0;

    foreach ($data['crops'][$set] as $hex => $crop) {
        [$inset, $x, $y, $size] = array_map(intval(...), explode(' ', $crop));
        [$left, $top, $right, $bottom] = $bounds['entries'][(string) $hex];

        // Balanced: a symmetric inset no larger than any margin.
        expect($inset)->toBeLessThanOrEqual(min($left, $top, $right, $bottom), "{$set} {$hex}");
        // Tight: a square inside the canvas that contains the whole artwork box.
        expect($x)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual($left, "{$set} {$hex}")
            ->and($y)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual($top, "{$set} {$hex}")
            ->and($x + $size)->toBeLessThanOrEqual(1000)->toBeGreaterThanOrEqual(1000 - $right, "{$set} {$hex}")
            ->and($y + $size)->toBeLessThanOrEqual(1000)->toBeGreaterThanOrEqual(1000 - $bottom, "{$set} {$hex}");
        $checked++;
    }

    // Every measured image has a crop; a set whose crops silently vanished fails here.
    expect($checked)->toBe(count($bounds['entries']))->toBeGreaterThan(2900);
})->with(['twemoji', 'noto', 'openmoji', 'fluent']);

it('removes padding from a padded set without changing relative size', function (): void {
    // OpenMoji draws faces with ~16% empty border; Balanced removes the set's common border (~9.5%) from both
    // the face and the small diamond, so the diamond stays proportionally smaller than the face.
    $face = emojis()->text('😀')->imageSet('openmoji')->toImages();
    $diamond = emojis()->text('🔹')->imageSet('openmoji')->toImages();

    preg_match('/viewBox="(\d+) (\d+) (\d+) (\d+)"/', $face, $f);
    preg_match('/viewBox="(\d+) (\d+) (\d+) (\d+)"/', $diamond, $d);

    expect($face)->toStartWith('<svg ')
        ->and($f[1])->toBe($d[1])
        ->and($f[3])->toBe($d[3])
        ->and((int) $f[3])->toBeLessThan(1000);
});
