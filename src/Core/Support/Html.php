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

    /**
     * True when the text is nothing but numeric character references ("&#x1F44B;&#128075;"), which are
     * already HTML and mean the same in plain text; escaping them again would print "&amp;#x1F44B;".
     *
     * @internal shared by the converter and the Blade component, so the two cannot disagree
     */
    public static function isCharacterReferences(string $text): bool
    {
        return preg_match('/\A(?:&#(?:x[0-9A-Fa-f]{1,6}|[0-9]{1,7});)+\z/', $text) === 1;
    }

    public function toHtml(): string
    {
        return $this->html;
    }
}
