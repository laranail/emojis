<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Extension;

use Stringable;
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;
use Simtabi\Laranail\Emojis\Core\Image\ImagePolicy;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidImage;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidCustomEmoji;

/**
 * An image-only emoji that Unicode does not have — Slack/Discord-style ":laravel:". It parses from its
 * shortcode (and optional aliases), renders as an <img> in Image mode and as its shortcode everywhere else,
 * unless a Unicode fallback is given for the text modes.
 *
 * The image is an EmojiImage: an https or root-relative URL, or a PNG/GIF/JPEG/WebP/SVG data URI that has
 * been size-checked, type-sniffed and (for SVG) sanitised.
 */
final readonly class CustomEmoji implements Stringable
{
    public EmojiImage $image;

    /**
     * @param list<string> $aliases
     *
     * @throws InvalidCustomEmoji for a bad name
     * @throws InvalidImage for an image the policy refuses
     */
    public function __construct(
        public string $name,
        EmojiImage|string $image,
        public ?string $fallback = null,
        public array $aliases = [],
        public ?string $label = null,
        ImagePolicy $policy = new ImagePolicy,
    ) {
        foreach ([$name, ...$aliases] as $code) {
            if (preg_match('/\A[a-z0-9_+\-]+\z/', $code) !== 1) {
                throw InvalidCustomEmoji::badName($code);
            }
        }

        $this->image = EmojiImage::from($image, $policy);
    }

    public function __toString(): string
    {
        return ':' . $this->name . ':';
    }

    public function label(): string
    {
        return $this->label ?? str_replace(['_', '-'], ' ', $this->name);
    }
}
