<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Exceptions;

use InvalidArgumentException;

/**
 * An emoji image was refused. The message names the rule that failed and never contains the image, its
 * URL or any part of its content: the input is untrusted, and exception messages end up in logs.
 */
final class InvalidImage extends InvalidArgumentException implements EmojisException
{
    public static function tooLarge(int $max): self
    {
        return new self(sprintf('Emoji image is larger than the %d-byte limit.', $max));
    }

    public static function tooManyPixels(int $maxDimension): self
    {
        return new self(sprintf('Emoji image is wider or taller than %d pixels.', $maxDimension));
    }

    public static function unsupportedType(): self
    {
        return new self('Emoji image is not a PNG, GIF, JPEG, WebP or SVG.');
    }

    public static function typeMismatch(string $declared, string $actual): self
    {
        return new self(sprintf('Emoji image is declared as %s but its content is %s.', $declared, $actual));
    }

    public static function malformed(string $what): self
    {
        return new self(sprintf('Emoji image is malformed: %s.', $what));
    }

    public static function svgDisabled(): self
    {
        return new self('SVG emoji images are disabled by the image policy.');
    }

    public static function svgNeedsDom(): self
    {
        return new self('SVG emoji images need ext-dom to be sanitised; refusing rather than passing SVG through unsanitised.');
    }

    public static function unsafeSvg(string $rule): self
    {
        return new self(sprintf('SVG emoji image refused: %s.', $rule));
    }

    public static function badUrl(string $rule): self
    {
        return new self(sprintf('Emoji image URL refused: %s.', $rule));
    }

    public static function unreadable(): self
    {
        return new self('Emoji image file is missing or unreadable.');
    }
}
