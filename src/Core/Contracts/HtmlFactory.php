<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Contracts;

use Stringable;

/**
 * Wraps rendered HTML in the host's "already safe" type. Core returns its own Html value; the Laravel
 * layer returns Illuminate\Support\HtmlString, so Blade's {{ }} does not escape it a second time.
 */
interface HtmlFactory
{
    public function make(string $html): Stringable;
}
