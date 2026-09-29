<?php

declare(strict_types=1);

/*
 * laranail/emojis — published to config/laranail/emojis.php, read as config('laranail.emojis.*').
 * Strings, numbers and lists only, so `php artisan config:cache` can serialise it. Reference:
 * https://opensource.simtabi.com/documentation/laranail/emojis/configuration
 */
return [

    'locale' => [
        'default'  => null,     // null follows app()->getLocale() on every call; a tag ('fr') pins one
        'fallback' => 'en',
    ],

    'shortcodes' => [
        'preset'     => 'github', // output vocabulary: github, emojibase, slack, joypixels, cldr (all are parsed)
        'delimiters' => [':', ':'],
    ],

    'images' => [
        'set'       => 'twemoji',  // twemoji, noto, openmoji, fluent, joypixels, or a registered set
        'source'    => 'cdn',      // cdn, or local after `php artisan laranail::emojis.images install <set>`
        'fit'       => 'balanced', // balanced, tight, none
        'class'     => '',         // extra classes, after the package's own laranail-emoji laranail-emoji-image
        'base_urls' => [],         // your own mirror: ['twemoji' => 'https://cdn.example.com/twemoji/svg']

        // Limits for images you supply: custom emoji, extend.images, Emojis::image(), EmojiImageRule.
        'custom' => [
            'max_bytes'     => 262144, // decoded size
            'max_dimension' => 1024,   // px, read from the header — never decoded
            'svg'           => true,   // sanitised, then only ever rendered inside <img>
            'hosts'         => [],     // allowed https hosts for image URLs; empty allows any
        ],
    ],

    'output' => [
        'name_template' => '[{name}]', // Mode::Name; must contain {name}
        'auto_fallback' => 'ascii',    // Mode::Auto where the terminal cannot draw emoji
        'degradation'   => [],         // per-target fallback chains: ['text' => ['shortcode', 'ascii']]
    ],

    'input' => [
        'max_bytes' => 1048576, // larger input throws InvalidInput

        // Emoticons never to match, list or write, packaged or added: [':P', '^^']. To match an opt-in-only
        // one without opting in to all of them, add it under extend.emoticons instead.
        'disabled_emoticons' => [],
    ],

    // Additions, registered at boot and frozen after it.
    'extend' => [
        'custom'     => [], // image-only emoji: 'laravel' => ['image' => 'https://… or data:image/…', 'aliases' => [], 'label' => 'Laravel']
        'images'     => [], // your image for an existing emoji: 'thumbsup' => 'https://… or data:image/png;base64,…'
        'shortcodes' => [], // 'shipit' => '1F680'
        'emoticons'  => [], // ':3' => '1F63A'
    ],

    // What user content may contain (Emojis::sanitize(), EmojiPolicyRule); the security rules always apply.
    // Keys: allow_groups, deny_groups, deny_subgroups, allow_only, deny, max_version, allow_unknown,
    // allow_custom, max_emojis, replacement.
    'policy' => [],

];
