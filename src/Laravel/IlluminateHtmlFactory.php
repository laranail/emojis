<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel;

use Stringable;
use Illuminate\Support\HtmlString;
use Simtabi\Laranail\Emojis\Core\Contracts\HtmlFactory;

/** Returns HtmlString, which Blade's {{ }} recognises as already escaped. */
final class IlluminateHtmlFactory implements HtmlFactory
{
    public function make(string $html): Stringable
    {
        return new HtmlString($html);
    }
}
