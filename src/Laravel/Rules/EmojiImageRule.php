<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Rules;

use Closure;
use SplFileInfo;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidImage;

/**
 * Accepts an emoji image the configured image policy allows: an uploaded file (any SplFileInfo, which
 * Laravel's UploadedFile is), a data URI, or an https
 * URL. The failure message names the rule that failed (size, type, dimensions, unsafe SVG), never the input.
 *
 * Validation is the check, not the storage: keep the EmojiImage it produces (`Emojis::image($value)`) rather
 * than the raw input, so an SVG is stored in its sanitised form.
 */
final readonly class EmojiImageRule implements ValidationRule
{
    public function __construct(private ?Emojis $emojis = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $emojis = $this->emojis ?? app(Emojis::class);

        try {
            match (true) {
                $value instanceof SplFileInfo => EmojiImage::fromFile((string) $value->getRealPath(), $emojis->options()->imagePolicy),
                is_string($value)             => $emojis->image($value),
                default                       => throw InvalidImage::malformed('not a file or a string'),
            };
        } catch (InvalidImage $e) {
            $fail('laranail/emojis::validation.emoji_image')->translate(['reason' => $e->getMessage()]);
        }
    }
}
