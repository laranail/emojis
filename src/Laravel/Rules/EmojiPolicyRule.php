<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Rules;

use Closure;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Security\Threat;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Emojis\Core\Security\EmojiPolicy;

/**
 * Rejects text containing an emoji the policy does not allow, or more emoji than it allows. Without an
 * argument it uses the configured policy (config('laranail.emojis.policy')).
 *
 *     'bio' => [new EmojiPolicyRule(EmojiPolicy::permissive()->denyGroups(Group::Flags)->maxEmojis(5))]
 */
final readonly class EmojiPolicyRule implements ValidationRule
{
    public function __construct(private ?EmojiPolicy $policy = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('laranail/emojis::validation.emoji_policy')->translate();

            return;
        }

        $emojis = app(Emojis::class);
        $report = $emojis->sanitize($value)->policy($this->policy ?? $emojis->options()->policy)->report();

        if ($report->has(Threat::Policy) || $report->has(Threat::Limit) || $report->has(Threat::Oversized)) {
            $fail('laranail/emojis::validation.emoji_policy')->translate();
        }
    }
}
