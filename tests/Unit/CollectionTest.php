<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Exceptions\EmojiNotFound;

it('ships a Japanese collection, starting with the Japanese-text buttons', function (): void {
    $japanese = emojis()->collection('japanese');

    expect(emojis()->collections())->toContain('japanese')
        ->and($japanese->count())->toBe(55)
        ->and((string) $japanese->first())->toBe('🈁')
        ->and($japanese->render())->toContain('🗾')
        ->and($japanese->first()?->name('ja'))->toBe('ココのマーク');
});

it('filters a query by collection', function (): void {
    expect(emojis()->query()->inCollection('japanese')->search('castle')->first()?->char)->toBe('🏯')
        ->and(emojis()->query()->inCollection('nope')->count())->toBe(0);
});

it('throws for an unknown collection', function (): void {
    emojis()->collection('nope');
})->throws(EmojiNotFound::class);
