<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Exceptions;

use InvalidArgumentException;

final class InvalidCustomEmoji extends InvalidArgumentException implements EmojisException
{
    public static function badName(string $name): self
    {
        return new self(sprintf('Custom emoji name "%s" must match [a-z0-9_+-]+.', $name));
    }

    public static function unsafeUrl(string $name): self
    {
        return new self(sprintf('Custom emoji "%s" has an image URL that is not https, root-relative or a data:image URI.', $name));
    }

    public static function frozen(): self
    {
        return new self('Custom emoji are registered at boot. The registry is frozen, so a per-request registration cannot leak into the next request.');
    }

    public static function collides(string $name): self
    {
        return new self(sprintf('Custom emoji "%s" collides with a Unicode emoji shortcode. Choose another name.', $name));
    }
}
