<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Text;

use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Exceptions\UnsupportedConversion;

/** What the scanner looks for. */
final readonly class ScanOptions
{
    /** @param list<Mode> $sources */
    public function __construct(
        public array $sources = [Mode::Unicode, Mode::Shortcode],
        public bool $riskyEmoticons = false,
        public bool $textPresentation = false,
    ) {
        foreach ($sources as $source) {
            if (! $source->isSource()) {
                throw UnsupportedConversion::notASource($source);
            }
        }
    }

    public function has(Mode $source): bool
    {
        return in_array($source, $this->sources, true);
    }
}
