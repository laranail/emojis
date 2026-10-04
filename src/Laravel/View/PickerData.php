<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View;

/**
 * Which picker payloads this request has already written into the page, so ten pickers in one locale embed
 * the payload once. Bound scoped, so it starts empty on every request, Octane included.
 *
 * Writing once per request assumes the first picker's markup reaches the page. Where it may not — a picker
 * inside a cached fragment, or one that first appears in a Livewire update — render
 * <x-laranail-emojis::picker-data /> where it always reaches the page (the layout), or turn on the HTTP API.
 */
final class PickerData
{
    /** @var array<string, true> */
    private array $written = [];

    /** The id of a locale's JSON data block, which the mount point's data-laranail-emoji-payload names. */
    public static function elementId(string $locale): string
    {
        return 'laranail-emoji-picker-data-' . $locale;
    }

    /** True the first time a locale is claimed in this request. */
    public function claim(string $locale): bool
    {
        if (isset($this->written[$locale])) {
            return false;
        }

        return $this->written[$locale] = true;
    }

    /**
     * A locale's data block the first time it is asked for in this request, and '' after, unless `always`.
     * The JSON escapes <, >, & and quotes, so nothing in it (a custom emoji's label) can close the element.
     */
    public function block(string $locale, PickerPayloads $payloads, bool $always = false): string
    {
        if (! $this->claim($locale) && ! $always) {
            return '';
        }

        $json = json_encode($payloads->payload($locale), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return '<script type="application/json" id="' . e(self::elementId($locale)) . "\">{$json}</script>";
    }
}
