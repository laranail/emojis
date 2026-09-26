<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Render\ImageSets;

use Closure;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Contracts\ImageSet;

/**
 * An image set addressed by a base URL and a filename rule. Every built-in set is one of these; they
 * differ only in URL, filename rule, coverage bit and licence, so one class serves all of them (the
 * exhaustive match lives in ImageSets, per laranail's driver-seam rule).
 *
 * Coverage comes from the dataset: an emoji whose file the set does not publish returns null rather than
 * a URL that 404s, and the renderer degrades it.
 */
final readonly class CdnImageSet implements ImageSet
{
    /** @param Closure(Emoji): ?string $filename returns the path below the base URL, or null */
    public function __construct(
        private string $name,
        private string $baseUrl,
        private Closure $filename,
        private int $coverageBit,
        private string $licence,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function url(Emoji $emoji): ?string
    {
        if ($this->coverageBit !== 0 && ($emoji->imageCoverage & $this->coverageBit) === 0) {
            return null;
        }

        $path = ($this->filename)($emoji);

        return $path === null ? null : rtrim($this->baseUrl, '/') . '/' . $path;
    }

    public function licence(): string
    {
        return $this->licence;
    }
}
