<?php

declare(strict_types=1);

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
        ->and(count($plain))->toBeGreaterThanOrEqual(147)
        ->and($plain)->not->toHaveKey('XD')
        ->and($all)->toHaveKey('XD')
        ->and((string) $plain[':]'])->toBe('🙂')
        ->and((string) $all['o_O'])->toBe('🤨');

    foreach ($all as $emoticon => $emoji) {
        expect((string) emojis()->fromEmoticon($emoticon))->toBe((string) $emoji, $emoticon);
    }
});

it('includes emoticons added at runtime', function (): void {
    $emojis = emojis()->addEmoticon('=^_^=', 'cat face');

    expect((string) $emojis->emoticons()['=^_^='])->toBe('🐱');
});

it('keeps the emoticon each emoji is written as', function (): void {
    // Adding a smiley must never change what toEmoticons() writes for an emoji that already had one.
    expect(emojis()->text('😂 😭 😳 😽 🤩 🤬 😧 😓')->toEmoticons())->toBe(":') :'o :$ :3 *_* :@ :s :<");
});
