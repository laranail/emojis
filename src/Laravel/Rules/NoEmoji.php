<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Rules;

use Simtabi\Laranail\Emojis\Core\Text\TextConverter;

/** The value contains no emoji — known, unknown, or stray modifiers and variation selectors. */
final class NoEmoji extends EmojiRule
{
    protected function passes(TextConverter $text): bool
    {
        return ! $text->contains(includeUnknown: true);
    }

    protected function message(): string
    {
        return 'laranail/emojis::validation.no_emoji';
    }
}
