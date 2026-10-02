<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;

// addShortcode() changes the instance, so each test builds its own rather than using the suite's shared one.
$fresh = static fn (): Emojis => Emojis::create(terminal: new EnvTerminalProbe(override: true));

it('adds an alias without changing what is written', function () use ($fresh): void {
    $emojis = $fresh()->addShortcode('shipit', 'rocket');

    expect($emojis->text(':shipit:')->toEmoji())->toBe('🚀')
        ->and($emojis->text('🚀')->toShortcodes())->toBe(':rocket:');
});

it('remaps an existing shortcode for reading and writing alike, so text round-trips', function () use ($fresh): void {
    $emojis = $fresh()->addShortcode('rocket', 'grinning face');
    $written = $emojis->text('🚀 😀')->toShortcodes();

    expect($emojis->text(':rocket:')->toEmoji())->toBe('😀')
        ->and($written)->not->toContain(':rocket: :grinning:')
        ->and($emojis->text($written)->toEmoji())->toBe('🚀 😀');
});

it('writes another of the emoji\'s codes when one is remapped away', function () use ($fresh): void {
    $emojis = $fresh()->addShortcode('thumbsup', 'rocket');
    $written = $emojis->text('👍')->toShortcodes();

    expect($written)->not->toBe(':thumbsup:')
        ->and($emojis->text($written)->toEmoji())->toBe('👍');
});

it('stays seven-bit in Ascii mode when every code is remapped away', function () use ($fresh): void {
    $emojis = $fresh()->addShortcode('rocket', 'grinning face');

    expect($emojis->text('🚀')->toAscii())->toBe('U+1F680')
        ->and($emojis->text('🚀')->toShortcodes())->toBe('🚀');
});
