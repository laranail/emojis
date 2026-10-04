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
 *
 * `rule` names the filename rule when it is a pure function of the hexcode (twemoji, noto, openmoji,
 * joypixels), so a browser can rebuild every URL from the base, the rule and the suffix — the picker
 * payload then carries those three instead of a URL per emoji. Null when it is not (Fluent's folder names).
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
        private ?string $rule = null,
        private string $suffix = '',
    ) {}

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /** The named filename rule a browser can apply to a hexcode, or null when only the server can. */
    public function rule(): ?string
    {
        return $this->rule;
    }

    /** What follows the rule's output in a filename: ".svg", ".png". */
    public function suffix(): string
    {
        return $this->suffix;
    }

    /** Whether the set publishes an image for the emoji, by the dataset's coverage (true when not measured). */
    public function covers(Emoji $emoji): bool
    {
        return $this->path($emoji) !== null;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function url(Emoji $emoji): ?string
    {
        $path = $this->path($emoji);

        return $path === null ? null : rtrim($this->baseUrl, '/') . '/' . $path;
    }

    /** The emoji's file below the base URL (URL-encoded), or null when the set has no image for it. */
    public function path(Emoji $emoji): ?string
    {
        if ($this->coverageBit !== 0 && ($emoji->imageCoverage & $this->coverageBit) === 0) {
            return null;
        }

        return ($this->filename)($emoji);
    }

    public function licence(): string
    {
        return $this->licence;
    }
}
