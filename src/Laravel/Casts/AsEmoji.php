<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Casts;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Stores one emoji as its hexcode ("1F44B-1F3FD") and hydrates it to an Emoji. The hexcode is ASCII, so the
 * column works on any charset — including MySQL utf8mb3, which cannot hold a four-byte emoji at all.
 *
 *     protected function casts(): array { return ['reaction' => AsEmoji::class]; }
 *
 * @implements CastsAttributes<Emoji|null, Emoji|string|null>
 */
final class AsEmoji implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Emoji
    {
        return is_string($value) && $value !== '' ? app(Emojis::class)->fromHexcode($value) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $emoji = $value instanceof Emoji ? $value : (is_string($value) ? app(Emojis::class)->find($value) : null);

        if ($emoji === null) {
            throw new InvalidArgumentException("The {$key} attribute must be a single emoji, hexcode or shortcode.");
        }

        return $emoji->hexcode;
    }
}
