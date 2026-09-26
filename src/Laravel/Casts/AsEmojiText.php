<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Casts;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Stores free text with its emoji as shortcodes and reads it back with emoji — for columns whose charset
 * cannot hold four-byte characters (MySQL utf8mb3), or where search should match ":rocket:".
 *
 *     protected function casts(): array { return ['bio' => AsEmojiText::class]; }
 *
 * The write side uses Mode::Ascii, which guarantees seven-bit output for every emoji in the dataset.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class AsEmojiText implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) ? app(Emojis::class)->text($value)->from(Mode::Shortcode)->to(Mode::Emoji) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) ? app(Emojis::class)->text($value)->from(Mode::Unicode)->to(Mode::Ascii) : null;
    }
}
