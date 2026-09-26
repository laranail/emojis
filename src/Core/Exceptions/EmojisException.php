<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Exceptions;

use Throwable;

/**
 * Marker for every exception this package throws, so a caller can catch the package as a whole.
 *
 * Messages and context never include the caller's input text (failure standard rule 15): an emoji
 * converter sees chat messages, names and comments, which are personal data.
 */
interface EmojisException extends Throwable {}
