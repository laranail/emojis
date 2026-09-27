<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Rules;

use Closure;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects text carrying invisible or reordering content: bytes smuggled in variation selectors or tag
 * characters, Trojan Source bidi controls, zero-width fillers, combining floods, orphan emoji components,
 * control characters or invalid UTF-8. Ordinary text and emoji in any language pass.
 *
 * Reject rather than clean when the value is an identifier (a username, a slug) or when silently changing
 * what the user typed would surprise them; otherwise prefer Emojis::sanitize().
 */
final class NoHiddenCharacters implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(Emojis::class)->sanitize($value)->isSafe()) {
            $fail('laranail/emojis::validation.no_hidden_characters')->translate();
        }
    }
}
