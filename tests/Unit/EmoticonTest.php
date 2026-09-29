<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;

// emojis() is shared across the suite; a test that adds or removes an emoticon needs its own instance.
function freshEmojis(): Emojis
{
    return Emojis::create(terminal: new EnvTerminalProbe(override: true));
}

it('converts the common smileys', function (string $text, string $expected): void {
    expect(emojis()->text($text)->withEmoticons()->toEmoji())->toBe($expected);
})->with([
    'square smile'    => [':] :-] =]', '🙂 🙂 🙂'],
    'grins and winks' => ['=D ;D :))', '😀 😉 😄'],
    'frowns'          => ['=( :[ :-{', '🙁 🙁 🙁'],
    'tears'           => [";( :'-( ;-;", '😢 😢 😭'],
    'faces'           => ['^^ ^.^ -.- >_< *-*', '😊 😊 😑 😣 🤩'],
    'hyphenated'      => [':-S :-$ :-@ :-3 >:-D', '😧 😳 🤬 😽 😈'],
]);

it('keeps letter- and digit-led smileys behind the opt-in', function (): void {
    $text = 'o_O O_o T_T x_x 0:) 8-)';

    expect(emojis()->text($text)->withEmoticons()->toEmoji())->toBe($text)
        ->and(emojis()->text($text)->withEmoticons(risky: true)->toEmoji())->toBe('🤨 🤨 😭 😵 😇 😎');
});

it('does not match the new smileys inside code or words', function (string $text): void {
    expect(emojis()->text($text)->withEmoticons()->toEmoji())->toBe($text);
})->with([
    'assignment'   => ['x =D1'],
    'call'         => ['f =(1)'],
    'caret power'  => ['2^^3'],
    'glued face'   => ['a>_<b'],
    'version dots' => ['v1.-.-2'],
]);

it('lists every emoticon, with the risky ones on request', function (): void {
    $plain = emojis()->emoticons();
    $all = emojis()->emoticons(risky: true);

    expect(count($all))->toBeGreaterThanOrEqual(198)
        ->and(count($plain))->toBeGreaterThanOrEqual(143)
        ->and($plain)->not->toHaveKey('XD')
        ->and($all)->toHaveKey('XD')
        ->and((string) $plain[':]'])->toBe('🙂')
        ->and((string) $all['o_O'])->toBe('🤨');

    foreach ($all as $emoticon => $emoji) {
        expect((string) emojis()->fromEmoticon($emoticon))->toBe((string) $emoji, $emoticon);
    }
});

it('includes emoticons added at runtime', function (): void {
    $emojis = freshEmojis()->addEmoticon('=^_^=', 'cat face');

    expect((string) $emojis->emoticons()['=^_^='])->toBe('🐱');
});

it('keeps the emoticon each emoji is written as', function (): void {
    // Adding a smiley must never change what toEmoticons() writes for an emoji that already had one.
    expect(emojis()->text('😂 😭 😳 😽 🤩 🤬 😧 😓')->toEmoticons())->toBe(":') :'o :$ :3 *_* :@ :s :<");
});

it('leaves prose-like emoticons alone unless opted in', function (string $text, string $risky): void {
    expect(emojis()->text($text)->withEmoticons()->toEmoji())->toBe($text)
        ->and(emojis()->text($text)->withEmoticons(risky: true)->toEmoji())->toBe($risky);
})->with([
    'yes or no'     => ['Continue? (y) or (n)', 'Continue? 👍 or 👎'],
    'question'      => ['what :? now', 'what 😒 now'],
    'arrow of text' => ['go <>< there', 'go 🐟 there'],
]);

it('still writes the emoticons it made opt-in', function (): void {
    expect(emojis()->text('👍 👎 😒 🐟')->toEmoticons())->toBe('(y) (n) :? <><');
});

it('matches an added emoticon without the opt-in, even one the dataset marks risky', function (): void {
    $emojis = freshEmojis()->addEmoticon('(y)', 'thumbs up');

    expect($emojis->text('ok (y) (n)')->withEmoticons()->toEmoji())->toBe('ok 👍 (n)')
        ->and($emojis->emoticons())->toHaveKey('(y)')
        ->and($emojis->emoticons())->not->toHaveKey('(n)');
});

it('lets a caller remap an emoticon', function (): void {
    $emojis = freshEmojis()->addEmoticon(':X', 'zipper-mouth face');

    expect($emojis->text('secret :X')->withEmoticons()->toEmoji())->toBe('secret 🤐')
        ->and((string) $emojis->fromEmoticon(':X'))->toBe('🤐');
});

it('switches emoticons off everywhere', function (): void {
    $emojis = freshEmojis()->removeEmoticon(':)', '^^');

    expect($emojis->text(':) :-) ^^')->withEmoticons()->toEmoji())->toBe(':) 🙂 ^^')
        ->and($emojis->fromEmoticon(':)'))->toBeNull()
        ->and($emojis->emoticons(risky: true))->not->toHaveKey(':)')
        ->and($emojis->get('🙂')->emoticons())->not->toContain(':)')
        ->and($emojis->text('🙂 😊')->toEmoticons())->toBe(':-) ^_^');
});

it('degrades an emoji whose every emoticon is switched off', function (): void {
    expect(freshEmojis()->removeEmoticon('</3')->text('💔')->toEmoticons())->toBe(':broken_heart:');
});

it('does not write a primary that now reads back as another emoji', function (): void {
    $emojis = freshEmojis()->addEmoticon(':)', 'grinning face');

    expect($emojis->text('🙂')->toEmoticons())->toBe(':-)')
        ->and($emojis->text(':)')->withEmoticons()->toEmoji())->toBe('😀');
});

it('lets a later add undo a removal', function (): void {
    $emojis = freshEmojis()->removeEmoticon('<3')->addEmoticon('<3', 'red heart');

    expect($emojis->text('<3')->withEmoticons()->toEmoji())->toBe('❤️');
});

it('refreshes an emoji\'s emoticons after one is added', function (): void {
    $emojis = freshEmojis();
    $before = $emojis->get('🐱')->emoticons();
    $emojis->addEmoticon('=^_^=', 'cat face');

    expect($before)->not->toContain('=^_^=')
        ->and($emojis->get('🐱')->emoticons())->toContain('=^_^=');
});
