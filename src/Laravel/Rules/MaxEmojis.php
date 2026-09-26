<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Rules;

use InvalidArgumentException;
use Simtabi\Laranail\Emojis\Core\Text\TextConverter;

/** The value contains at most $max emoji, unknown pictographs included. */
final class MaxEmojis extends EmojiRule
{
    public function __construct(private readonly int $max)
    {
        if ($max < 0) {
            throw new InvalidArgumentException('MaxEmojis needs a non-negative maximum.');
        }
    }

    protected function passes(TextConverter $text): bool
    {
        return $text->count(includeUnknown: true) <= $this->max;
    }

    protected function message(): string
    {
        return 'laranail/emojis::validation.max_emojis';
    }

    protected function replacements(): array
    {
        return ['max' => (string) $this->max];
    }
}
