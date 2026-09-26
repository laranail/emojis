<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Exceptions;

use InvalidArgumentException;

/** A lookup by id, hexcode, character or shortcode matched nothing. find() returns null instead. */
final class EmojiNotFound extends InvalidArgumentException implements EmojisException
{
    public static function for(string $kind, string $key): self
    {
        // Lookup keys are identifiers the caller chose (a shortcode, a hexcode), not free text, but they
        // are still truncated so a mistaken call with a whole message cannot put it in a log.
        return new self(sprintf('No emoji matches %s "%s".', $kind, mb_strimwidth($key, 0, 40, '…')));
    }
}
