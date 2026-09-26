<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Extension;

use Stringable;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidCustomEmoji;

/**
 * An image-only emoji that Unicode does not have — Slack/Discord-style ":laravel:". It parses from its
 * shortcode (and optional aliases), renders as an <img> in Image mode and as its shortcode everywhere else,
 * unless a Unicode fallback is given for the text modes.
 */
final readonly class CustomEmoji implements Stringable
{
    /** @param list<string> $aliases */
    public function __construct(
        public string $name,
        public string $imageUrl,
        public ?string $fallback = null,
        public array $aliases = [],
        public ?string $label = null,
    ) {
        foreach ([$name, ...$aliases] as $code) {
            if (preg_match('/^[a-z0-9_+\-]+$/', $code) !== 1) {
                throw InvalidCustomEmoji::badName($code);
            }
        }

        if (! self::isSafeUrl($imageUrl)) {
            throw InvalidCustomEmoji::unsafeUrl($name);
        }
    }

    public function __toString(): string
    {
        return ':' . $this->name . ':';
    }

    /** https://, protocol-relative-free root paths, or a data:image URI. Never javascript:, never http:. */
    public static function isSafeUrl(string $url): bool
    {
        return preg_match('#^(?:https://[^\s"\'<>]+|/(?!/)[^\s"\'<>]*|data:image/(?:png|gif|webp|avif|svg\+xml);base64,[A-Za-z0-9+/=]+)$#', $url) === 1;
    }

    public function label(): string
    {
        return $this->label ?? str_replace(['_', '-'], ' ', $this->name);
    }
}
