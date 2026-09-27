<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/**
 * The Japanese mobile carriers whose emoji came before Unicode's, and Google's bridge encoding for them.
 *
 * Their emoji still turn up as private-use code points — PHP's own `SJIS-Mobile#DOCOMO`, `#KDDI` and
 * `#SOFTBANK` (and `UTF-8-Mobile#…`) mbstring encodings produce exactly these. The three carriers' ranges
 * overlap, so reading one always needs the carrier named; Google's plane-15 range is unambiguous.
 */
enum Carrier: string
{
    /** NTT docomo, U+E63E–U+E757. */
    case Docomo = 'docomo';

    /** au by KDDI, U+E468–U+EB88. */
    case Au = 'au';

    /** SoftBank (formerly J-Phone / Vodafone), U+E001–U+E537. */
    case SoftBank = 'softbank';

    /** Google's supplementary private-use mapping, U+FE000–U+FEEA0, used by Gmail and early Android. */
    case Google = 'google';
}
