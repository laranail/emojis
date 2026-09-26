<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Exceptions;

use Throwable;
use RuntimeException;

/**
 * The shipped dataset is missing, unreadable or not the shape this code expects. Critical under the
 * failure standard: continuing would convert text with a partial catalogue and report success.
 */
final class DatasetException extends RuntimeException implements EmojisException
{
    public static function unreadable(string $shard, string $path, ?Throwable $previous = null): self
    {
        return new self(sprintf('Emoji dataset shard "%s" could not be loaded from %s.', $shard, $path), previous: $previous);
    }

    public static function malformed(string $shard, string $expected): self
    {
        return new self(sprintf('Emoji dataset shard "%s" is malformed: expected %s.', $shard, $expected));
    }
}
