<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Livewire;

use Livewire\Component;
use Livewire\Attributes\Modelable;

/**
 * <livewire:laranail-emojis.picker wire:model="body" />
 * <livewire:laranail-emojis.picker wire:model="body" locale="fr" placeholder="Say hi" :rows="3" />
 *
 * A textarea with the emoji picker beside it, its value bound to the parent's property. Registered only when
 * Livewire is installed. It is thin on purpose: the picker inserts at the caret and dispatches `input`, which
 * wire:model already listens for, so the Blade picker pointed at any wire:model'd field works the same way
 * without this component. Recents and the chosen tone stay in the browser; nothing round-trips per pick.
 *
 * The target is an attribute selector, not "#id": Livewire ids are random alphanumerics, and one in six
 * starts with a digit, which "#id" cannot express — querySelector throws on it.
 */
final class EmojiPicker extends Component
{
    #[Modelable]
    public string $value = '';

    public ?string $locale = null;

    public string $placeholder = '';

    public int $rows = 3;

    public function render(): string
    {
        return <<<'BLADE'
            <div class="laranail-emoji-picker-field">
                <textarea id="{{ $this->getId() }}-input" wire:model="value" rows="{{ $rows }}" placeholder="{{ $placeholder }}"></textarea>
                <div wire:ignore>
                    <x-laranail-emojis::picker :target="'[id=\'' . $this->getId() . '-input\']'" :locale="$locale" />
                </div>
            </div>
            BLADE;
    }
}
