<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/**
 * Where image-set files are served from. `Cdn` points at the pinned jsDelivr release; `Local` at the copy
 * `laranail::emojis.images install` verified and wrote under public/vendor/laranail/emojis/images, so pages
 * make no third-party request (privacy, availability, a strict img-src).
 */
enum ImageSource: string
{
    case Cdn = 'cdn';
    case Local = 'local';
}
