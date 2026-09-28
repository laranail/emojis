<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Image;

use InvalidArgumentException;

/**
 * Limits applied to every image a caller supplies — a data URI, base64, a file or a URL. The defaults suit
 * an emoji: small, square-ish, never large enough to be a decompression bomb.
 *
 * URLs are referenced, never fetched: the package emits them in an <img src> and makes no request itself,
 * so there is no server-side request forgery to guard against. `hosts` optionally narrows which hosts a
 * URL may name.
 */
final readonly class ImagePolicy
{
    /** @param list<string> $hosts allowed URL hosts, lowercase; empty allows any https host */
    public function __construct(
        public int $maxBytes = 262_144,
        public int $maxDimension = 1024,
        public bool $allowSvg = true,
        public int $maxSvgElements = 20_000,
        public array $hosts = [],
    ) {
        if ($maxBytes < 1 || $maxDimension < 1 || $maxSvgElements < 1) {
            throw new InvalidArgumentException('Image policy limits must be positive.');
        }
    }

    /** @param array<string, mixed> $config the `images.custom` group */
    public static function fromArray(array $config): self
    {
        $int = static fn (string $key, int $default): int => is_int($config[$key] ?? null) ? $config[$key] : $default;
        $hosts = [];

        foreach (is_array($config['hosts'] ?? null) ? $config['hosts'] : [] as $host) {
            if (is_string($host) && $host !== '') {
                $hosts[] = strtolower($host);
            }
        }

        return new self(
            maxBytes: $int('max_bytes', 262_144),
            maxDimension: $int('max_dimension', 1024),
            allowSvg: ! array_key_exists('svg', $config) || $config['svg'] === true,
            maxSvgElements: $int('max_svg_elements', 20_000),
            hosts: $hosts,
        );
    }
}
