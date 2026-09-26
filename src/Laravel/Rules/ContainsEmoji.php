<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Rules;

use Simtabi\Laranail\Emojis\Core\Text\TextConverter;

/** The value contains at least one emoji the dataset knows. */
final class ContainsEmoji extends EmojiRule
{
    protected function passes(TextConverter $text): bool
    {
        return $text->contains(includeUnknown: false);
    }

    protected function message(): string
    {
        return 'laranail/emojis::validation.contains_emoji';
    }
}
