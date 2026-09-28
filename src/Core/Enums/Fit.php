<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/**
 * How an emoji image fills its box. Image sets draw inside a safe area — OpenMoji leaves about 16% of the
 * canvas empty on every side, Noto and Fluent about 5–6%, Twemoji almost none — which shows up as uneven,
 * oversized gaps around emoji in running text and buttons.
 *
 * The crops come from measuring every pinned image (.dev/tools/measure), so they never cut into artwork.
 */
enum Fit: string
{
    /** The published artwork, padding and all, as a plain <img>. */
    case None = 'none';

    /**
     * Remove the set's common padding, capped per emoji at that emoji's own margin, as a centred square.
     * Every emoji in a set keeps the same visual scale — a small symbol stays small — without the empty
     * border. The default for web output.
     */
    case Balanced = 'balanced';

    /** Crop each emoji to its own artwork, as a centred square: maximum fill, for avatars and reactions. */
    case Tight = 'tight';
}
