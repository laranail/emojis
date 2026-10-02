<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Tags\Tag;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\TagRole;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;
use Simtabi\Laranail\Emojis\Core\Exceptions\UnsupportedConversion;

it('reads the shard in the field order it was written with', function (): void {
    expect(emojis()->dataset()->tags()['fields'])->toBe(['group', 'role', 'symbol', 'emoji', 'aliases']);
});

it('ships every tag in a known group, findable by label and alias, with a symbol that is not an emoji', function (): void {
    $tags = emojis()->tags();
    $all = $tags->all();
    $names = [];

    expect(count($all))->toBeGreaterThanOrEqual(70)
        ->and(array_keys($tags->groups()))->toBe(['outcome', 'severity', 'task', 'test', 'change', 'misc']);

    foreach ($all as $tag) {
        expect($tags->groups())->toHaveKey($tag->group)
            ->and($tags->get($tag->label))->toBe($tag)
            ->and(emojis()->fromChar($tag->symbol))->toBeNull()
            ->and(mb_strlen($tag->symbol))->toBe(1)
            ->and($tags->for($tag->primaryEmoji()))->toBe($tag);

        foreach ([$tag->label, ...$tag->aliases] as $name) {
            expect(isset($names[$name]))->toBeFalse("{$name} is used twice");
            $names[$name] = true;
            expect($tags->get($name))->toBe($tag);
        }
    }

    expect(array_sum(array_map(static fn (string $group): int => count($tags->group($group)), array_keys($tags->groups()))))->toBe(count($all));
});

it('finds a tag in any case, with or without brackets, and by alias', function (string $key, ?string $label): void {
    expect(emojis()->tags()->get($key)?->label)->toBe($label);
})->with([
    ['OK', 'OK'], ['ok', 'OK'], ['[OK]', 'OK'], [' [warn] ', 'WARNING'], ['n/a', 'N/A'], ['timed out', 'TIMEOUT'], ['nope', null],
]);

it('maps emoji to tags, a toned emoji through its base, and anything Emojis::find() takes', function (): void {
    $tags = emojis()->tags();

    expect($tags->for('✅')?->label)->toBe('OK')
        ->and($tags->for('🆗')?->label)->toBe('OK')
        ->and($tags->for('👍🏽')?->label)->toBe('YES')
        ->and($tags->for(':warning:')?->label)->toBe('WARNING')
        ->and($tags->for('🚀'))->toBeNull()
        ->and($tags->for('no such thing'))->toBeNull()
        ->and(emojis()->get('❌')->tag()?->role)->toBe(TagRole::Danger);
});

it('searches labels and aliases, prefix matches first, with 0 meaning no limit', function (): void {
    $labels = static fn (array $found): array => array_map(static fn (Tag $t): string => $t->label, $found);

    expect($labels(emojis()->tags()->search('fail')))->toBe(['FAIL'])
        ->and($labels(emojis()->tags()->search('[ERR]')))->toBe(['ERROR'])
        ->and(emojis()->tags()->search(''))->toBe([])
        ->and(count(emojis()->tags()->search('E', 2)))->toBe(2)
        ->and(count(emojis()->tags()->search('E')))->toBeGreaterThan(2);
});

it('writes emoji as tags in Mode::Tag, degrading the rest to names', function (): void {
    expect(emojis()->text('✅ Deployed, ⚠️ 2 warnings, ❌ 1 failed, 🚀')->to(Mode::Tag))->toBe('[OK] Deployed, [WARNING] 2 warnings, [FAIL] 1 failed, [rocket]')
        ->and(emojis()->text('🚀')->degrade(Mode::Tag, Mode::Shortcode)->to(Mode::Tag))->toBe(':rocket:')
        ->and(static fn (): string => emojis()->text('🚀')->strict()->to(Mode::Tag))->toThrow(UnsupportedConversion::class)
        ->and(emojis()->text('✅')->strict()->to(Mode::Tag))->toBe('[OK]');
});

it('uses the configured tag template, and refuses one without {tag}', function (): void {
    expect(Emojis::create(['output' => ['tag_template' => '{tag}:']])->text('✅ ok')->to(Mode::Tag))->toBe('OK: ok')
        ->and(static fn (): Emojis => Emojis::create(['output' => ['tag_template' => '[]']]))->toThrow(InvalidArgumentException::class, '{tag}');
});

it('is a target only, and not ASCII-safe, since it degrades to a localized name', function (): void {
    expect(Mode::Tag->isSource())->toBeFalse()
        ->and(Mode::Tag->isAsciiSafe())->toBeFalse()
        ->and(static fn (): string => emojis()->text('[OK]')->from(Mode::Tag)->toEmoji())->toThrow(UnsupportedConversion::class);
});

it('serialises a tag for JSON consumers', function (): void {
    expect(json_decode((string) json_encode(emojis()->tags()->get('OK')), true))->toMatchArray([
        'label' => 'OK', 'text' => '[OK]', 'group' => 'outcome', 'role' => 'success', 'symbol' => '✓', 'emoji' => ['✅', '👌'], 'aliases' => ['OKAY'],
    ]);
});

it('falls back to tags under Mode::Auto when configured to, as the console recipe shows', function (): void {
    $emojis = Emojis::create(['output' => ['auto_fallback' => 'tag']], terminal: new EnvTerminalProbe(override: false));

    expect($emojis->text('✅ Deployed 🚀')->to(Mode::Auto))->toBe('[OK] Deployed [rocket]')
        ->and(emojis()->text('✅ Deployed 🚀')->to(Mode::Tag))->toBe('[OK] Deployed [rocket]');
});
