<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/** Shortcode vocabularies. Parsing always accepts every preset; output uses the one chosen. */
enum ShortcodePreset: string
{
    case GitHub = 'github';
    case Emojibase = 'emojibase';
    case Slack = 'slack';
    case JoyPixels = 'joypixels';
    case Cldr = 'cldr';
}
