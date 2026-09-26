<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/**
 * How a sequence found in text is qualified (UTS #51 ED-18/18a/19). The catalogue holds only
 * fully-qualified emoji and components; the other forms are accepted as input and normalised.
 */
enum Qualification: int
{
    case FullyQualified = 0;
    case MinimallyQualified = 1;
    case Unqualified = 2;
    case Component = 3;

    /** A single character whose default presentation is text (©, ®, ™, ☺). Matched only on request. */
    case TextDefault = 4;
}
