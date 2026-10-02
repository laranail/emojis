<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Kaomoji;

it('ships Google’s faces and Japanese kaomoji, deduplicated', function (): void {
    $all = emojis()->kaomoji();
    $values = array_map(static fn (Kaomoji $k): string => $k->value, $all);

    expect(count($all))->toBeGreaterThan(2000)
        ->and(count(array_unique($values)))->toBe(count($values))
        ->and(array_filter($values, static fn (string $v): bool => str_contains($v, "\n")))->toBeEmpty();
});

it('finds Japanese kaomoji by kana reading and tag', function (): void {
    $cats = emojis()->searchKaomoji('ねこ');

    expect($cats)->not->toBeEmpty()
        ->and($cats[0]->readings)->not->toBeEmpty()
        ->and(emojis()->searchKaomoji('shrug'))->not->toBeEmpty();
});

it('groups Japanese kaomoji under ja_ groups with Japanese labels', function (): void {
    $groups = emojis()->kaomojiGroups();

    expect($groups)->toHaveKey('ja_cute')
        ->and($groups['ja_cute'])->toBe('可愛い')
        ->and(emojis()->kaomoji('ja_cute'))->not->toBeEmpty();
});

it('treats a search limit of 0 as no limit, as Emojis::search() does', function (): void {
    expect(count(emojis()->searchKaomoji('happy', 0)))->toBeGreaterThan(1)
        ->and(count(emojis()->searchKaomoji('happy', 0)))->toBe(count(emojis()->searchKaomoji('happy', PHP_INT_MAX)))
        ->and(count(emojis()->search('face', limit: 0)))->toBeGreaterThan(24);
});
