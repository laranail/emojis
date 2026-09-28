<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Image;

use Stringable;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidImage;

/**
 * An image a caller supplies for an emoji — a PNG, GIF, JPEG, WebP or SVG given as a data URI, base64,
 * raw bytes, a file, or an https URL — validated once, here, and safe to put in an <img src> afterwards.
 *
 * Embedded images (everything but a URL) are checked in this order: size before decoding, strict base64,
 * type sniffed from the content and matched against any declared type, then dimensions read from the header
 * (never by decoding pixels) for rasters, or sanitisation for SVG. The result is always re-encoded as a
 * base64 data URI, so the bytes that render are the bytes that were checked.
 *
 * A URL is referenced, not fetched — the package makes no request, so there is no SSRF — and must be https
 * or root-relative, with no credentials, whitespace, control characters or backslashes, optionally limited
 * to the policy's hosts. Browsers load <img> content without running script, which is the final layer.
 */
final readonly class EmojiImage implements Stringable
{
    private function __construct(
        public string $src,
        public ?ImageType $type,
        public ?int $width,
        public ?int $height,
        public ?string $sha256,
    ) {}

    public function __toString(): string
    {
        return $this->src;
    }

    /**
     * Any supported form: an existing EmojiImage, a data URI, or a URL. Files and bare base64 are
     * ambiguous with URLs and must use their own constructors.
     */
    public static function from(self|string $image, ImagePolicy $policy = new ImagePolicy): self
    {
        if ($image instanceof self) {
            return $image;
        }

        return str_starts_with(ltrim($image), 'data:') ? self::fromDataUri($image, $policy) : self::fromUrl($image, $policy);
    }

    public static function fromDataUri(string $uri, ImagePolicy $policy = new ImagePolicy): self
    {
        // The encoded form of the largest allowed image, plus room for the header: refuse before decoding.
        if (strlen($uri) > intdiv($policy->maxBytes * 4, 3) + 256) {
            throw InvalidImage::tooLarge($policy->maxBytes);
        }

        if (preg_match('/\Adata:(image\/[a-z0-9.+-]+)((?:;[a-z0-9-]+=[a-z0-9.-]+)*)(;base64)?,(.*)\z/is', trim($uri), $m) !== 1) {
            throw InvalidImage::malformed('not an image data URI');
        }

        $bytes = $m[3] !== '' ? self::decodeBase64($m[4]) : rawurldecode($m[4]);

        return self::fromBytes($bytes, $m[1], $policy);
    }

    public static function fromBase64(string $base64, ?string $mime = null, ImagePolicy $policy = new ImagePolicy): self
    {
        if (strlen($base64) > intdiv($policy->maxBytes * 4, 3) + 8) {
            throw InvalidImage::tooLarge($policy->maxBytes);
        }

        return self::fromBytes(self::decodeBase64($base64), $mime, $policy);
    }

    public static function fromFile(string $path, ImagePolicy $policy = new ImagePolicy): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw InvalidImage::unreadable();
        }

        $size = filesize($path);

        if ($size === false || $size > $policy->maxBytes) {
            throw InvalidImage::tooLarge($policy->maxBytes);
        }

        $bytes = file_get_contents($path);

        if ($bytes === false) {
            throw InvalidImage::unreadable();
        }

        return self::fromBytes($bytes, null, $policy);
    }

    /** @param string|null $mime the declared type, if any; it must agree with the content */
    public static function fromBytes(string $bytes, ?string $mime = null, ImagePolicy $policy = new ImagePolicy): self
    {
        if ($bytes === '') {
            throw InvalidImage::malformed('empty');
        }

        if (strlen($bytes) > $policy->maxBytes) {
            throw InvalidImage::tooLarge($policy->maxBytes);
        }

        $type = RasterInspector::sniff($bytes) ?? throw InvalidImage::unsupportedType();

        if ($mime !== null) {
            $declared = ImageType::fromMime($mime) ?? throw InvalidImage::unsupportedType();

            if ($declared !== $type) {
                throw InvalidImage::typeMismatch($declared->mime(), $type->mime());
            }
        }

        if ($type === ImageType::Svg) {
            if (! $policy->allowSvg) {
                throw InvalidImage::svgDisabled();
            }

            $bytes = SvgSanitizer::sanitize($bytes, $policy->maxSvgElements);
            $width = $height = null;
        } else {
            [$width, $height] = RasterInspector::dimensions($type, $bytes);

            if ($width > $policy->maxDimension || $height > $policy->maxDimension) {
                throw InvalidImage::tooManyPixels($policy->maxDimension);
            }
        }

        return new self('data:' . $type->mime() . ';base64,' . base64_encode($bytes), $type, $width, $height, hash('sha256', $bytes));
    }

    public static function fromUrl(string $url, ImagePolicy $policy = new ImagePolicy): self
    {
        if ($url === '' || strlen($url) > 2048) {
            throw InvalidImage::badUrl('empty or longer than 2048 characters');
        }

        // Whitespace, controls, quotes, angle brackets and backslashes: browsers "repair" these in ways that
        // turn a path into another host (/\evil.com) or break out of the attribute.
        if (preg_match('/[\x00-\x20\x7F"\'<>\\\\`]/', $url) === 1) {
            throw InvalidImage::badUrl('contains whitespace, a control character, a quote or a backslash');
        }

        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//')) {
                throw InvalidImage::badUrl('protocol-relative URLs are not allowed');
            }

            return new self($url, null, null, null, null);
        }

        $parts = parse_url($url);

        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === '') {
            throw InvalidImage::badUrl('only https:// and root-relative URLs are allowed');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw InvalidImage::badUrl('credentials in the URL are not allowed');
        }

        if ($policy->hosts !== [] && ! in_array(strtolower($parts['host']), $policy->hosts, true)) {
            throw InvalidImage::badUrl('host is not in the allowed list');
        }

        return new self($url, null, null, null, null);
    }

    public function isEmbedded(): bool
    {
        return str_starts_with($this->src, 'data:');
    }

    private static function decodeBase64(string $data): string
    {
        // Line breaks and spaces are common in pasted base64; anything else outside the alphabet is refused.
        $data = (string) preg_replace('/[\r\n\t ]+/', '', rawurldecode($data));
        $bytes = base64_decode($data, true);

        if ($bytes === false) {
            throw InvalidImage::malformed('invalid base64');
        }

        return $bytes;
    }
}
