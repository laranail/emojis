<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\HtmlString;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Laravel\View\PickerPayloads;
use Simtabi\Laranail\Emojis\Laravel\View\PickerData as Written;

/**
 * <x-laranail-emojis::picker-data />
 * <x-laranail-emojis::picker-data locale="fr" />
 *
 * The picker payload's JSON data block for one locale, written unconditionally. A picker embeds the block
 * itself the first time a locale is used in a request, which fails when that picker's markup never reaches
 * the page — a cached fragment, or a picker that first appears in a Livewire update. Put this in the layout
 * and every picker on the page finds the block, wherever it was rendered. Pickers rendered after it in the
 * same request do not embed it again.
 */
final class PickerData extends Component
{
    public function __construct(
        private readonly Emojis $emojis,
        private readonly PickerPayloads $payloads,
        private readonly Written $written,
        public ?string $locale = null,
    ) {}

    public function render(): HtmlString
    {
        return new HtmlString($this->written->block($this->emojis->locales()->resolve($this->locale), $this->payloads, always: true));
    }
}
