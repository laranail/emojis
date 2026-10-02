<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Support;

/**
 * Reads an integer config value. A string of digits counts, because env() returns strings and
 * `'max_bytes' => env('EMOJI_MAX_BYTES', 262144)` is the ordinary way to set one; anything else is the default.
 *
 * @internal
 */
final class ConfigInt
{
    /**
     * @template T of int|null
     *
     * @param T $default
     *
     * @return int|T
     */
    public static function read(mixed $value, ?int $default): ?int
    {
        return match (true) {
            is_int($value)                                                  => $value,
            is_string($value) && preg_match('/\A\d{1,18}\z/', $value) === 1 => (int) $value,
            default                                                         => $default,
        };
    }
}
