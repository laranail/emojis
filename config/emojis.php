<?php

declare(strict_types=1);

/*
 * laranail/emojis — published to config/laranail/emojis.php, read as config('laranail.emojis.*').
 *
 * Every value is a plain scalar or list so `php artisan config:cache` can serialise it: modes and presets are
 * their string values, never enum instances, and nothing here is a closure.
 */
return [

    // null follows the application locale on every call (app()->getLocale()); a tag pins one.
    'locale'          => null,
    'fallback_locale' => 'en',

    // github · emojibase · slack · joypixels · cldr. Parsing accepts every preset; output uses this one.
    'shortcode_preset'     => 'github',
    'shortcode_delimiters' => [':', ':'],

    // twemoji · noto · openmoji · fluent · joypixels, or a set registered with Emojis::addImageSet().
    // Check the set's licence before shipping: Twemoji is CC-BY 4.0 and OpenMoji CC BY-SA 4.0 (attribution
    // required); JoyPixels' free licence excludes commercial use.
    'image_set' => 'twemoji',

    // Serve a set from your own host instead of jsDelivr: ['twemoji' => 'https://cdn.example.com/twemoji/svg'].
    'image_base_urls' => [],

    'image_class' => 'emoji',

    // Mode::Name output. Must contain {name}.
    'name_template' => '[{name}]',

    // Fallback chains when a target cannot represent an emoji. Omitted targets keep the built-in chain
    // (text → unicode, emoticon → shortcode, image → unicode, emoji → shortcode).
    'degradation' => [
        // 'text' => ['shortcode', 'ascii'],
    ],

    // What Mode::Auto becomes when the terminal cannot show emoji. LARANAIL_EMOJIS=1|0 forces detection.
    'auto_fallback' => 'ascii',

    // Conversions refuse larger input (InvalidInput), so a pasted novel cannot pin a worker.
    'max_input_bytes' => 1048576,

    // Image-only custom emoji: 'laravel' => ['url' => 'https://…/laravel.svg', 'fallback' => null,
    // 'aliases' => [], 'label' => 'Laravel']. URLs must be https, root-relative or data:image.
    'custom' => [],

    // Extra shortcodes and emoticons for existing emoji: 'shipit' => '1F680', ':3' => '1F63A'.
    'shortcodes' => [],
    'emoticons'  => [],

];
