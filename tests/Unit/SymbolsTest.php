<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Symbols\Symbol;

// The symbol catalogue: Unicode's non-emoji special characters in nine groups.

const INVISIBLE_CATEGORIES = ['Cc', 'Cf', 'Co', 'Cs', 'Cn', 'Zs', 'Zl', 'Zp', 'Mn', 'Mc', 'Me'];

it('has every group, each non-trivially filled', function (): void {
    $symbols = emojis()->symbols();

    expect($symbols->groups())->toBe(['popular', 'arrows', 'currency', 'math', 'numbers', 'punctuation', 'letters', 'symbols', 'hieroglyphs'])
        ->and($symbols->count())->toBeGreaterThan(7000);

    foreach ($symbols->groups() as $group) {
        expect(count($symbols->group($group)))->toBeGreaterThan(50, $group);
    }
});

it('never contains an invisible, control, private-use or combining character', function (): void {
    $symbols = emojis()->symbols();
    $inspected = 0;

    foreach ($symbols->groups() as $group) {
        foreach ($symbols->group($group) as $symbol) {
            $inspected++;
            expect(in_array($symbol->category, INVISIBLE_CATEGORIES, true))->toBeFalse($symbol->unicode());
        }
    }

    expect($inspected)->toBeGreaterThan(7000);
});

it('looks a symbol up by character, U+ code or hex, and writes it every way', function (): void {
    $arrow = emojis()->symbols()->get('→');

    expect($arrow?->name)->toBe('rightwards arrow')
        ->and($arrow?->label())->toBe('Rightwards arrow')
        ->and($arrow?->unicode())->toBe('U+2192')
        ->and($arrow?->htmlEntity())->toBe('&rarr;')
        ->and($arrow?->css())->toBe('\2192')
        ->and($arrow?->javascript())->toBe('\u2192')
        ->and($arrow?->php())->toBe('\u{2192}')
        ->and(emojis()->symbols()->get('U+2192'))->toBe($arrow)
        ->and(emojis()->symbols()->get('2192'))->toBe($arrow)
        ->and(emojis()->symbols()->get('€')?->block)->toBe('Currency Symbols')
        ->and(emojis()->symbols()->get('𓀀')?->javascript())->toBe('\uD80C\uDC00')
        ->and(emojis()->symbols()->get('𓀀')?->htmlEntity())->toBe('&#x13000;')
        ->and(emojis()->symbols()->get("\u{202E}"))->toBeNull()
        ->and(emojis()->symbols()->get("\u{200B}"))->toBeNull();
});

it('searches names by every word, prefix matches first', function (): void {
    $found = array_map(static fn (Symbol $s): string => $s->char, emojis()->symbols()->search('double arrow', 'arrows', 5));

    expect($found)->toHaveCount(5)
        ->and(emojis()->symbols()->search('euro sign')[0]->char)->toBe('€')
        ->and(emojis()->symbols()->search(''))->toBe([]);
});

it('covers the currency signs written as ideographs and syllables', function (): void {
    $currency = array_map(static fn (Symbol $s): string => $s->char, emojis()->symbols()->group('currency'));

    expect($currency)->toContain('€', '£', '¥', '₹', '₿', '元', '円', '원');
});

it('lists a group\'s characters directly, in the same order as its symbols', function (): void {
    $symbols = emojis()->symbols();
    $inspected = 0;

    foreach ($symbols->groups() as $group) {
        $characters = $symbols->characters($group);

        expect($characters)->toBe(array_map(static fn (Symbol $s): string => $s->char, $symbols->group($group)), $group);
        $inspected += count($characters);
    }

    expect($inspected)->toBeGreaterThan(7000)
        ->and(array_slice($symbols->characters('arrows'), 0, 4))->toBe(['←', '↑', '→', '↓'])
        ->and($symbols->characters('popular')[0])->toBe('©')
        ->and($symbols->characters('nope'))->toBe([]);
});

it('stores each symbol as a readable record, in the field order the loader reads', function (): void {
    $shard = require dirname(__DIR__, 2) . '/database/generated/symbols.php';

    // Symbols reads records by position; this pins the positions to the names the generator writes.
    expect($shard['fields'])->toBe(['char', 'name', 'category', 'block', 'entity'])
        ->and(count($shard['symbols']))->toBeGreaterThan(7000);

    foreach ($shard['symbols'] as $hex => $record) {
        expect($record[0])->toBe(mb_chr((int) hexdec((string) $hex), 'UTF-8'), (string) $hex);
    }

    expect(str_starts_with($shard['groups']['arrows'], '← ↑ → ↓'))->toBeTrue();
});
