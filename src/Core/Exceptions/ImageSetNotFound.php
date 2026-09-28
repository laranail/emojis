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

    public static function notInstallable(string $name): self
    {
        return new self(sprintf('Image set "%s" cannot be installed locally: only twemoji, noto, openmoji and fluent ship the hashes that verify a download (JoyPixels\' licence does not allow redistribution).', $name));
    }
}
