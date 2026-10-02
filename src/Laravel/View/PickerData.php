<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View;

/**
 * Which picker payloads this request has already written into the page, so ten pickers in one locale embed
 * the payload once. Bound scoped, so it starts empty on every request, Octane included.
 */
final class PickerData
{
    /** @var array<string, true> */
    private array $written = [];

    /** True the first time a locale is claimed in this request. */
    public function claim(string $locale): bool
    {
        if (isset($this->written[$locale])) {
            return false;
        }

        return $this->written[$locale] = true;
    }
}
