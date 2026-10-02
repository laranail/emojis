<?php

declare(strict_types=1);

// The emoji picker's interface strings. Publish the translations (`--tag=laranail::emojis-translations`)
// to add a language; group names come from the catalogue, and emoji names and keywords from CLDR.
return [
    'search'     => 'Search emoji',
    'results'    => '{count} results',
    'no_results' => 'No emoji found',
    'recent'     => 'Frequently used',
    'custom'     => 'Custom',
    'tone'       => 'Skin tone',
    'tones'      => ['Default', 'Light', 'Medium-light', 'Medium', 'Medium-dark', 'Dark'],
    'open'       => 'Choose an emoji',
    'loading'    => 'Loading emoji',
    'failed'     => 'Emoji could not be loaded',
    'no_script'  => 'The emoji picker needs JavaScript. You can still type emoji directly.',
];
