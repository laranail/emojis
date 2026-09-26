<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Contracts;

use Simtabi\Laranail\Emojis\Core\Emoji;

/** A source of emoji images, addressed by URL. Nothing is bundled; sets point at a CDN or a self-hosted copy. */
interface ImageSet
{
    public function name(): string;

    /** The URL of the emoji's image, or null when this set has no image for it. */
    public function url(Emoji $emoji): ?string;

    /** The licence the images are published under, for docs and attribution footers. */
    public function licence(): string;
}
