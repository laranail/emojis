<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Exceptions;

use LogicException;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;

/** Strict conversion was requested and the target mode cannot represent the emoji, or a version cap hides it. */
final class UnsupportedConversion extends LogicException implements EmojisException
{
    public static function for(string $hexcode, Mode $target): self
    {
        return new self(sprintf('Emoji %s has no %s form and strict mode forbids degrading it.', $hexcode, $target->value));
    }

    /** The emoji has a $target form, but it is newer than the version cap and strict mode forbids degrading it. */
    public static function versionCapped(string $hexcode, Mode $target, EmojiVersion $cap): self
    {
        return new self(sprintf('Emoji %s is newer than the Emoji %s cap, so its %s form is not shown, and strict mode forbids degrading it.', $hexcode, $cap->value, $target->value));
    }

    public static function notASource(Mode $mode): self
    {
        return new self(sprintf('Mode "%s" is a target only and cannot be parsed from text.', $mode->value));
    }
}
