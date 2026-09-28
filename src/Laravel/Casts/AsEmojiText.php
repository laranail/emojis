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
 * The value round-trips: a shortcode the user typed as text (":rocket:") is stored behind a backslash
 * ("\:rocket:") so reading does not turn it into 🚀, and backslashes already sitting in front of a shortcode
 * are doubled so that marker stays unambiguous. Backslashes anywhere else are stored as typed, so a row
 * written before this marker existed reads back exactly as it always did.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class AsEmojiText implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $emojis = app(Emojis::class);
        $out = '';
        $cursor = 0;

        foreach ($emojis->text($value)->from(Mode::Shortcode)->extract() as $match) {
            $between = substr($value, $cursor, $match->offset - $cursor);
            $slashes = strlen($between) - strlen(rtrim($between, '\\'));
            $out .= substr($between, 0, strlen($between) - $slashes) . str_repeat('\\', intdiv($slashes, 2));
            $out .= $slashes % 2 === 1 ? $match->text : $emojis->text($match->text)->from(Mode::Shortcode)->to(Mode::Emoji);
            $cursor = $match->offset + $match->length;
        }

        return $out . substr($value, $cursor);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $emojis = app(Emojis::class);
        $out = '';
        $cursor = 0;

        foreach ($emojis->text($value)->from(Mode::Unicode, Mode::Shortcode)->extract() as $match) {
            $between = substr($value, $cursor, $match->offset - $cursor);
            $slashes = strlen($between) - strlen(rtrim($between, '\\'));
            $out .= $between . str_repeat('\\', $slashes);

            if ($match->source === Mode::Shortcode) {
                $out .= '\\' . $match->text;
            } else {
                $out .= $emojis->text($match->text)->from(Mode::Unicode)->to(Mode::Ascii);
            }

            $cursor = $match->offset + $match->length;
        }

        return $out . substr($value, $cursor);
    }
}
