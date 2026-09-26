<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/** The UTS #51 sequence kind of an emoji. */
enum SequenceType: string
{
    case Basic = 'basic';
    case Keycap = 'keycap';
    case Flag = 'flag';
    case Tag = 'tag';
    case Modifier = 'modifier';
    case Zwj = 'zwj';
}
