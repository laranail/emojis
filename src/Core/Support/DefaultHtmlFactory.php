<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Support;

use Stringable;
use Simtabi\Laranail\Emojis\Core\Contracts\HtmlFactory;

final class DefaultHtmlFactory implements HtmlFactory
{
    public function make(string $html): Stringable
    {
        return new Html($html);
    }
}
