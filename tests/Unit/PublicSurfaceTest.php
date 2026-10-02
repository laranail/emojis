<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\Subgroup;
use Simtabi\Laranail\Emojis\Core\Enums\EscapeFormat;
use Simtabi\Laranail\Emojis\Core\Enums\SequenceType;
use Simtabi\Laranail\Emojis\Core\Security\EmojiPolicy;
use Simtabi\Laranail\Emojis\Core\Catalogue\EmojiCollection;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;

// Public methods the 0.4 sweep found no test referencing. Each assertion pins documented behaviour.

it('looks up by hexcode and shortcode, reports its dataset, and resolves Auto from the terminal', function (): void {
    expect(emojis()->fromHexcode('1f680')?->char)->toBe('🚀')
        ->and(emojis()->fromShortcode(':rocket:')?->char)->toBe('🚀')
        ->and(emojis()->fromShortcode('no_such_code'))->toBeNull()
        ->and(emojis()->datasetVersion())->toMatch('/\d/')
        ->and(Emojis::create(terminal: new EnvTerminalProbe(override: true))->resolveAuto())->toBe(Mode::Emoji)
        ->and(Emojis::create(terminal: new EnvTerminalProbe(override: false))->resolveAuto())->toBe(Mode::Ascii);
});

it('reads extra sources, writes Unicode and code points, and collects the emoji it found', function (): void {
    $converter = emojis()->text(':rocket: &#x1F44B;')->from(Mode::Shortcode)->alsoFrom(Mode::HtmlEntity);

    expect($converter->toUnicode())->toBe('🚀 👋')
        ->and(emojis()->text('🚀')->toCodepoints())->toBe('U+1F680')
        ->and($converter->emojis()->hexcodes())->toBe(['1F680', '1F44B']);
});

it('compares, classifies and describes a single emoji', function (): void {
    $rocket = emojis()->get('rocket');

    expect($rocket->is('🚀'))->toBeTrue()
        ->and($rocket->is(emojis()->get('wave')))->toBeFalse()
        ->and(emojis()->flag('KE')->isFlag())->toBeTrue()
        ->and($rocket->isFlag())->toBeFalse()
        ->and($rocket->keywords())->toContain('space')
        ->and($rocket->shortcodes())->toContain('rocket')
        ->and($rocket->allShortcodes())->toContain('rocket')
        ->and($rocket->toHtmlEntity())->toBe('&#x1F680;')
        ->and($rocket->escaped(EscapeFormat::JavaScript))->toBe('\uD83D\uDE80');
});

it('filters a query by subgroup, type, components, tones, text presentation and page', function (): void {
    $query = emojis()->query();

    expect($query->subgroup(Subgroup::FaceSmiling)->get()->first()?->char)->toBe('😀')
        ->and($query->type(SequenceType::Flag)->count())->toBeGreaterThan(200)
        ->and($query->withComponents()->count())->toBeGreaterThan($query->count())
        ->and($query->skinToneable(true)->get()->filter(static fn (Emoji $e): bool => ! $e->supportsSkinTones())->isEmpty())->toBeTrue()
        ->and($query->withTextPresentation(true)->get()->filter(static fn (Emoji $e): bool => ! $e->hasTextPresentation)->isEmpty())->toBeTrue()
        ->and($query->limit(3)->offset(1)->get()->hexcodes())->toBe($query->get()->skip(1)->take(3)->hexcodes());
});

it('slices, maps, de-duplicates and groups a collection', function (): void {
    $collection = new EmojiCollection([emojis()->get('🚀'), emojis()->get('😀'), emojis()->get('🚀')]);

    expect($collection->last()?->char)->toBe('🚀')
        ->and(new EmojiCollection()->isEmpty())->toBeTrue()
        ->and($collection->filter(static fn (Emoji $e): bool => $e->char === '😀')->count())->toBe(1)
        ->and($collection->map(static fn (Emoji $e): string => $e->char))->toBe(['🚀', '😀', '🚀'])
        ->and($collection->take(1)->hexcodes())->toBe(['1F680'])
        ->and($collection->skip(2)->hexcodes())->toBe(['1F680'])
        ->and($collection->unique()->hexcodes())->toBe(['1F680', '1F600'])
        ->and(array_keys($collection->groupByGroup()))->toBe([Group::TravelAndPlaces->value, Group::SmileysAndEmotion->value]);
});

it('permits emoji by group, subgroup and custom origin', function (): void {
    $faces = EmojiPolicy::permissive()->allowGroups(Group::SmileysAndEmotion);
    $noSmiles = EmojiPolicy::permissive()->denySubgroups(Subgroup::FaceSmiling);
    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    $custom = Emojis::create(terminal: new EnvTerminalProbe(override: true))->addCustom('partyparrot', $png);

    expect($faces->permits(emojis()->get('😀')))->toBeTrue()
        ->and($faces->permits(emojis()->get('🚀')))->toBeFalse()
        ->and($noSmiles->permits(emojis()->get('😀')))->toBeFalse()
        ->and($noSmiles->permits(emojis()->get('🚀')))->toBeTrue()
        ->and($custom->sanitize('hi :partyparrot:')->policy(EmojiPolicy::permissive()->allowCustom(false))->clean())->toBe('hi ')
        ->and($custom->sanitize('hi :partyparrot:')->policy(EmojiPolicy::permissive()->allowCustom())->clean())->toBe('hi :partyparrot:');
});
