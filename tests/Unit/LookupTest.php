<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Kaomoji;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiId;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Enums\Subgroup;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Enums\SequenceType;
use Simtabi\Laranail\Emojis\Core\Exceptions\EmojiNotFound;

it('finds one emoji by every kind of key', function (string $key): void {
    expect(emojis()->get($key)->hexcode)->toBe('1F44B');
})->with([
    'character'         => '👋',
    'hexcode'           => '1F44B',
    'U+ hexcode'        => 'U+1F44B',
    'lowercase hexcode' => '1f44b',
    'shortcode'         => ':wave:',
    'bare shortcode'    => 'wave',
    'slug'              => 'waving_hand',
    'emoticon'          => 'o/',
]);

it('resolves an EmojiId case', function (): void {
    expect(emojis()->get(EmojiId::GrinningFace)->char)->toBe('😀');
});

it('accepts every qualification of a character', function (): void {
    expect(emojis()->get('☺')->hexcode)->toBe('263A-FE0F')
        ->and(emojis()->get("\u{263A}\u{FE0F}")->hexcode)->toBe('263A-FE0F')
        ->and(emojis()->get('2764')->hexcode)->toBe('2764-FE0F');
});

it('returns null from find and throws from get', function (): void {
    expect(emojis()->find('definitely-not-an-emoji'))->toBeNull();
    emojis()->get('definitely-not-an-emoji');
})->throws(EmojiNotFound::class);

it('exposes typed data', function (): void {
    $emoji = emojis()->get('👩🏽‍💻');

    expect($emoji->group)->toBe(Group::PeopleAndBody)
        ->and($emoji->subgroup)->toBe(Subgroup::PersonRole)
        ->and($emoji->type)->toBe(SequenceType::Zwj)
        ->and($emoji->tones)->toBe([SkinTone::Medium])
        ->and($emoji->base()->char)->toBe('👩‍💻')
        ->and($emoji->version)->toBe(EmojiVersion::V4_0);
});

it('applies skin tones, including one per person', function (): void {
    $handshake = emojis()->get('handshake');

    expect((string) emojis()->get('wave')->withSkinTone(SkinTone::Dark))->toBe('👋🏿')
        ->and((string) $handshake->withSkinTone(SkinTone::Light, SkinTone::Dark))->toBe('🫱🏻‍🫲🏿')
        ->and((string) $handshake->withSkinTone(SkinTone::Medium, SkinTone::Medium))->toBe('🤝🏽')
        ->and($handshake->skinTonePeople())->toBe(2)
        ->and($handshake->skinToneVariants())->toHaveCount(25)
        ->and(emojis()->get('👋🏿')->withoutSkinTone()->char)->toBe('👋');
});

it('refuses a skin tone the emoji does not take', function (): void {
    emojis()->get('rocket')->withSkinTone(SkinTone::Light);
})->throws(EmojiNotFound::class);

it('builds only RGI flags', function (): void {
    expect((string) emojis()->flag('ke'))->toBe('🇰🇪')
        ->and((string) emojis()->flag('GB-SCT'))->toBe('🏴󠁧󠁢󠁳󠁣󠁴󠁿')
        ->and((string) emojis()->flag('EU'))->toBe('🇪🇺');
});

it('does not invent flags', function (string $region): void {
    emojis()->flag($region);
})->with(['XX', 'UK', 'GB-XYZ'])->throws(EmojiNotFound::class);

it('queries, filters and ranks', function (): void {
    expect(emojis()->query()->flags()->count())->toBeGreaterThanOrEqual(258)
        ->and(emojis()->query()->since(EmojiVersion::V18_0)->count())->toBeGreaterThan(0)
        ->and(emojis()->query()->supportedBy(EmojiVersion::V1_0)->since(EmojiVersion::V2_0)->count())->toBe(0)
        ->and(emojis()->search('rocket')->first()?->char)->toBe('🚀')
        ->and(emojis()->search('fusée', 'fr')->first()?->char)->toBe('🚀')
        ->and(emojis()->query()->withSkinToneVariants()->count())->toBeGreaterThan(emojis()->query()->count());
});

it('lists kaomoji by group', function (): void {
    $shrugs = emojis()->kaomoji('shrugging');

    expect($shrugs)->not->toBeEmpty()
        ->and(emojis()->kaomojiGroups())->toHaveCount(15)
        ->and(array_filter(emojis()->kaomoji(asciiOnly: true), static fn (Kaomoji $k): bool => preg_match('/[^\x20-\x7E]/', $k->value) === 1))->toBeEmpty();
});
