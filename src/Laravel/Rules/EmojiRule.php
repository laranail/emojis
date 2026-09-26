<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Rules;

use Closure;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Emojis\Core\Text\TextConverter;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidInput;

/**
 * Shared shape of the emoji rules: non-strings and invalid UTF-8 fail with the rule's own message (they are
 * not emoji-free, and they are not emoji either), and detection counts pictographs the dataset does not know,
 * so an emoji newer than the dataset cannot slip past NoEmoji.
 */
abstract class EmojiRule implements ValidationRule
{
    final public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail($this->message())->translate();

            return;
        }

        try {
            $text = app(Emojis::class)->text($value)->from(Mode::Unicode);
        } catch (InvalidInput) {
            $fail($this->message())->translate();

            return;
        }

        if (! $this->passes($text)) {
            $fail($this->message())->translate($this->replacements());
        }
    }

    abstract protected function passes(TextConverter $text): bool;

    abstract protected function message(): string;

    /** @return array<string, string> */
    protected function replacements(): array
    {
        return [];
    }
}
