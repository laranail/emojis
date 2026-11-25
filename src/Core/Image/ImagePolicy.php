<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Image;

use InvalidArgumentException;
use Simtabi\Laranail\Emojis\Core\Support\ConfigInt;
use Simtabi\Laranail\Emojis\Core\Support\ConfigBool;

/**
 * Limits applied to every image a caller supplies — a data URI, base64, a file or a URL. The defaults suit
 * an emoji: small, square-ish, never large enough to be a decompression bomb.
 *
 * URLs are referenced, never fetched: the package emits them in an <img src> and makes no request itself,
 * so there is no server-side request forgery to guard against. The reader's browser does fetch them, though,
 * so a URL a user supplies can be a tracking pixel: `hosts` narrows which hosts a URL may name, and
 * `urls: false` refuses URLs altogether, leaving only inline images (data URIs, base64, files).
 *
 * A root-relative URL (`/img/a.png`) names the app's own origin, so the reader's browser sends it the app's
 * cookies: a user-supplied `/logout` or admin GET is a cross-site request forgery. It is accepted when no
 * `hosts` are listed, as before, and refused once `hosts` narrows URLs, unless `relative` opts in. Images the
 * application configures itself (`extend.custom`, `extend.images`) are trusted with root-relative URLs.
 */
final readonly class ImagePolicy
{
    /**
     * @param list<string> $hosts allowed URL hosts, lowercase; empty allows any https host
     * @param bool|null $allowRelative root-relative URLs; null allows them only when $hosts is empty
     */
    public function __construct(
        public int $maxBytes = 262_144,
        public int $maxDimension = 1024,
        public bool $allowSvg = true,
        public int $maxSvgElements = 20_000,
        public array $hosts = [],
        public bool $allowUrls = true,
        public ?bool $allowRelative = null,
    ) {
        if ($maxBytes < 1 || $maxDimension < 1 || $maxSvgElements < 1) {
            throw new InvalidArgumentException('Image policy limits must be positive.');
        }
    }

    /** @param array<string, mixed> $config the `images.custom` group */
    public static function fromArray(array $config): self
    {
        $int = static fn (string $key, int $default): int => ConfigInt::read($config[$key] ?? null, $default);
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
            allowUrls: ! array_key_exists('urls', $config) || $config['urls'] === true,
            allowRelative: self::relative($config['relative'] ?? null),
        );
    }

    /** The same policy with root-relative URLs allowed: for images the application configures itself. */
    public function withRelativeAllowed(): self
    {
        return new self($this->maxBytes, $this->maxDimension, $this->allowSvg, $this->maxSvgElements, $this->hosts, $this->allowUrls, true);
    }

    public function allowsRelative(): bool
    {
        return $this->allowRelative ?? $this->hosts === [];
    }

    private static function relative(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return ConfigBool::read($value) ?? throw new InvalidArgumentException('images.custom.relative must be a boolean or null.');
    }
}
