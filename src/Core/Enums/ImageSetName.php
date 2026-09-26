<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/** The built-in image sets. Custom sets register by name through ImageSets::register(). */
enum ImageSetName: string
{
    /** jdecked/twemoji — graphics CC-BY 4.0 (attribution required). The default. */
    case Twemoji = 'twemoji';

    /** Google Noto Emoji — Apache-2.0 / OFL. No country flags in the 2D set. */
    case Noto = 'noto';

    /** OpenMoji — CC BY-SA 4.0 (attribution and share-alike required). */
    case OpenMoji = 'openmoji';

    /** Microsoft Fluent Emoji — MIT. No flags. */
    case Fluent = 'fluent';

    /** JoyPixels — the free licence is personal use only; commercial use needs a paid licence. */
    case JoyPixels = 'joypixels';
}
