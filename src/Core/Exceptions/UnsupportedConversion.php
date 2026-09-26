<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Exceptions;

use LogicException;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;

/** Strict conversion was requested and the target mode cannot represent the emoji. */
final class UnsupportedConversion extends LogicException implements EmojisException
{
    public static function for(string $hexcode, Mode $target): self
    {
        return new self(sprintf('Emoji %s has no %s form and strict mode forbids degrading it.', $hexcode, $target->value));
    }

    public static function notASource(Mode $mode): self
    {
        return new self(sprintf('Mode "%s" is a target only and cannot be parsed from text.', $mode->value));
    }
}
