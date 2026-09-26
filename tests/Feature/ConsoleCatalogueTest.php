<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Console\Tools\Support\Emoji;
use Simtabi\Laranail\Console\Tools\Support\DisplayWidth;
use Simtabi\Laranail\Console\Tools\Support\EmojisBridge;
use Simtabi\Laranail\Emojis\Laravel\ConsoleEmojiCatalogue;

/*
 * laranail/console discovers this package's adapter by a class name it cannot import. These tests are
 * the other half of that contract: console's own suite runs against a fake, so only here are both
 * packages genuinely installed.
 */

beforeEach(fn () => EmojisBridge::reset());
afterEach(fn () => EmojisBridge::reset());

it('is the class console looks for', function (): void {
    expect(EmojisBridge::ADAPTER)->toBe(ConsoleEmojiCatalogue::class)
        ->and(EmojisBridge::catalogue())->toBeInstanceOf(ConsoleEmojiCatalogue::class);
});

it('resolves shortcodes beyond console\'s own map, and leaves console\'s names alone', function (): void {
    expect(Emoji::make()->unicode()->get('unicorn'))->toBe('🦄')
        ->and(Emoji::make()->ascii()->get('unicorn'))->toBe(':unicorn:')
        ->and(Emoji::make()->unicode()->get('cross'))->toBe('❌')
        ->and(Emoji::make()->ascii()->render('ship 🚀 🦄'))->toBe('ship -> :unicorn:')
        ->and(count(Emoji::make()->all()))->toBeGreaterThan(1000);
});

it('uses the application\'s configured instance', function (): void {
    app()->instance(Emojis::class, Emojis::create()->addShortcode('shipit', '🚀'));

    expect(Emoji::make()->unicode()->get('shipit'))->toBe('🚀');
});

it('makes console measure every emoji in the catalogue as this package does', function (): void {
    $checked = 0;
    $disagree = [];

    foreach (app(Emojis::class)->all() as $emoji) {
        $checked++;

        if (DisplayWidth::of($emoji->char) !== app(Emojis::class)->text($emoji->char)->from(Mode::Unicode)->width()) {
            $disagree[] = $emoji->hexcode;
        }
    }

    expect($checked)->toBeGreaterThan(3900)
        ->and($disagree)->toBe([]);
});
