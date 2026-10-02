<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;
use Simtabi\Laranail\Emojis\Core\Exceptions\UnsupportedConversion;

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

it('reads the shortcode delimiters it is configured to write', function (array $delimiters, string $rocket): void {
    $emojis = Emojis::create(['shortcodes' => ['delimiters' => $delimiters]], terminal: new EnvTerminalProbe(override: true));
    $written = $emojis->text('👋🏽 🚀')->toShortcodes();

    expect($written)->toContain($rocket)
        ->and($emojis->text($written)->toEmoji())->toBe('👋🏽 🚀')
        ->and($emojis->text('a' . $rocket . 'b')->toEmoji())->toBe('a' . $rocket . 'b');
})->with([
    'colons'          => [[':', ':'], ':rocket:'],
    'braces'          => [['{', '}'], '{rocket}'],
    'double brackets' => [['[[', ']]'], '[[rocket]]'],
    'regex-special'   => [['(*', '*)'], '(*rocket*)'],
]);

it('reads colons when a delimiter is configured empty, rather than every bare word', function (): void {
    $emojis = Emojis::create(['shortcodes' => ['delimiters' => ['', '']]], terminal: new EnvTerminalProbe(override: true));

    expect($emojis->text('rocket :rocket:')->toEmoji())->toBe('rocket 🚀');
});

it('applies a config array\'s extend and disabled emoticons in create(), as the provider does', function (): void {
    $emojis = Emojis::create([
        'extend' => ['shortcodes' => ['shipit' => 'rocket'], 'emoticons' => ['(rocket)' => 'rocket']],
        'input'  => ['disabled_emoticons' => [':)']],
    ], terminal: new EnvTerminalProbe(override: true));

    expect($emojis->text(':shipit:')->toEmoji())->toBe('🚀')
        ->and($emojis->fromEmoticon('(rocket)')?->hexcode)->toBe('1F680')
        ->and($emojis->fromEmoticon(':)'))->toBeNull();
});

it('writes an added emoticon for an emoji the dataset gives none, and reads it back', function () use ($fresh): void {
    $emojis = $fresh()->addEmoticon('(rocket)', 'rocket');

    expect($emojis->get('rocket')->emoticon())->toBe('(rocket)')
        ->and($emojis->text('🚀')->to(Mode::Emoticon))->toBe('(rocket)');
});

it('refuses, under strict(), to write a custom emoji\'s code where it has no form and no fallback', function () use ($fresh): void {
    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    $emojis = $fresh()->addCustom('partyparrot', $png)->addCustom('shipit_squirrel', $png, fallback: '🐿️');

    expect(static fn (): string => $emojis->text(':partyparrot:')->strict()->to(Mode::Unicode))->toThrow(UnsupportedConversion::class, ':partyparrot:')
        ->and($emojis->text(':partyparrot:')->to(Mode::Unicode))->toBe(':partyparrot:')
        ->and($emojis->text(':shipit_squirrel:')->strict()->to(Mode::Unicode))->toBe('🐿️')
        ->and($emojis->text(':partyparrot:')->strict()->to(Mode::Shortcode))->toBe(':partyparrot:');
});

it('keys custom emoji images by a fixed :name:, so stored HTML reads back whatever the delimiters become', function (): void {
    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    $braces = Emojis::create(['shortcodes' => ['delimiters' => ['{', '}']]], terminal: new EnvTerminalProbe(override: true))->addCustom('partyparrot', $png);
    $html = $braces->text('{partyparrot}')->to(Mode::Image);
    $colons = Emojis::create(terminal: new EnvTerminalProbe(override: true))->addCustom('partyparrot', $png);

    expect($html)->toContain('data-laranail-emoji=":partyparrot:"')->toContain('alt="{partyparrot}"')
        ->and($braces->text($html)->from(Mode::Image)->to(Mode::Shortcode))->toBe('{partyparrot}')
        ->and($colons->text($html)->from(Mode::Image)->to(Mode::Shortcode))->toBe(':partyparrot:');
});
