<?php

declare(strict_types=1);

// The emoji picker's interface strings and group names. Publish the translations
// (`--tag=laranail::emojis-translations`) to change them or add a language; emoji names and keywords come
// from CLDR. A file is named for the dataset's locale tag (`zh-Hant`, `pt`), which is what the picker asks
// for. `{count}` is replaced with the number of results; `results_one` is used when it is exactly one.
return [
    'search'      => 'Search emoji',
    'results'     => '{count} results',
    'results_one' => '1 result',
    'no_results'  => 'No emoji found',
    'recent'      => 'Frequently used',
    'custom'      => 'Custom',
    'tone'        => 'Skin tone',
    'tones'       => ['Default', 'Light', 'Medium-light', 'Medium', 'Medium-dark', 'Dark'],
    'open'        => 'Choose an emoji',
    'loading'     => 'Loading emoji',
    'failed'      => 'Emoji could not be loaded',
    'no_script'   => 'The emoji picker needs JavaScript. You can still type emoji directly.',
    'emoji'       => 'Emoji',
    'kaomoji'     => 'Kaomoji',
    'symbols'     => 'Symbols',
    'style'       => 'Emoji style',
    'native'      => 'Native',
    'groups'      => [
        'smileys_and_emotion' => 'Smileys & Emotion',
        'people_and_body'     => 'People & Body',
        'animals_and_nature'  => 'Animals & Nature',
        'food_and_drink'      => 'Food & Drink',
        'travel_and_places'   => 'Travel & Places',
        'activities'          => 'Activities',
        'objects'             => 'Objects',
        'symbols'             => 'Symbols',
        'flags'               => 'Flags',
    ],
];
