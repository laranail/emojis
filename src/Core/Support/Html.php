<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Support;

use Stringable;

/** Rendered HTML that is already escaped where it needs to be. Core's default HtmlFactory product. */
final readonly class Html implements Stringable
{
    public function __construct(private string $html) {}

    public function __toString(): string
    {
        return $this->html;
    }

    public function toHtml(): string
    {
        return $this->html;
    }
}
