<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Rules;

use Simtabi\Laranail\Emojis\Core\Text\TextConverter;

/** The value is exactly one known emoji (surrounding whitespace allowed) — a reaction field, say. */
final class SingleEmoji extends EmojiRule
{
    protected function passes(TextConverter $text): bool
    {
        return $text->isOnlyEmoji() && $text->count(includeUnknown: true) === 1 && $text->count() === 1;
    }

    protected function message(): string
    {
        return 'laranail/emojis::validation.single_emoji';
    }
}
