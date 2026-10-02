<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/** What a status tag means, for colouring it: the five roles every console and log viewer can show. */
enum TagRole: string
{
    case Success = 'success';
    case Danger = 'danger';
    case Warning = 'warning';
    case Info = 'info';
    case Muted = 'muted';
}
