<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Exceptions;

use InvalidArgumentException;

/** An emoji policy built from config that cannot be read safely. The message names the key, never a value. */
final class InvalidPolicy extends InvalidArgumentException implements EmojisException
{
    public static function notABoolean(string $key, mixed $value): self
    {
        return new self(sprintf('Emoji policy "%s" must be a boolean; got %s.', $key, get_debug_type($value)));
    }
}
