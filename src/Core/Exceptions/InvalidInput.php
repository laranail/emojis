<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Exceptions;

use InvalidArgumentException;

/** Input text that cannot be processed safely. The message describes the problem, never the text. */
final class InvalidInput extends InvalidArgumentException implements EmojisException
{
    public static function invalidUtf8(): self
    {
        return new self('Input is not valid UTF-8.');
    }

    public static function tooLarge(int $bytes, int $limit): self
    {
        return new self(sprintf('Input is %d bytes; the configured limit is %d.', $bytes, $limit));
    }
}
