<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Security;

use Stringable;

/** The cleaned text and the report of what was removed. */
final readonly class SanitizationResult implements Stringable
{
    public function __construct(
        public string $text,
        public SanitizationReport $report,
    ) {}

    public function __toString(): string
    {
        return $this->text;
    }
}
