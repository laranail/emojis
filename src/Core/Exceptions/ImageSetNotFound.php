<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Exceptions;

use InvalidArgumentException;

final class ImageSetNotFound extends InvalidArgumentException implements EmojisException
{
    /** @param list<string> $known */
    public static function named(string $name, array $known): self
    {
        return new self(sprintf('No emoji image set named "%s". Known: %s.', $name, implode(', ', $known)));
    }
}
