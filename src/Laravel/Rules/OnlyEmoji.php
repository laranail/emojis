<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Rules;

use Simtabi\Laranail\Emojis\Core\Text\TextConverter;

/** The value is one or more emoji and nothing else but whitespace. */
final class OnlyEmoji extends EmojiRule
{
    protected function passes(TextConverter $text): bool
    {
        return $text->isOnlyEmoji();
    }

    protected function message(): string
    {
        return 'laranail/emojis::validation.only_emoji';
    }
}
