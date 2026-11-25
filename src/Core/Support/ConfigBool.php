<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Support;

/**
 * A boolean from config, read the way env() and config files write one: true/false, 1/0, "true"/"false",
 * "1"/"0", "on"/"off", "yes"/"no". Anything else is null, so a caller can refuse it rather than guess:
 * reading a typo as "allow" turns a deny off without a word.
 */
final class ConfigBool
{
    public static function read(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 0 || $value === 1 ? $value === 1 : null;
        }

        return is_string($value) && $value !== '' ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
    }
}
