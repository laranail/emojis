<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Livewire;

use Livewire\Component;
use Livewire\Attributes\Locked;
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

    /** Locked: the picker inside is wire:ignore'd, so a locale changed from the browser would never show. */
    #[Locked]
    public ?string $locale = null;

    public string $placeholder = '';

    public int $rows = 3;

    /** The textarea's name, for a form that also posts without Livewire. */
    public ?string $name = null;

    /** The textarea's accessible name, when no <label> points at it. Defaults to the placeholder. */
    public ?string $label = null;

    public function render(): string
    {
        return <<<'BLADE'
            <div class="laranail-emoji-picker-field">
                <textarea id="{{ $this->getId() }}-input" wire:model="value" rows="{{ $rows }}" placeholder="{{ $placeholder }}" @if ($name) name="{{ $name }}" @endif aria-label="{{ $label ?? ($placeholder !== '' ? $placeholder : __('laranail/emojis::picker.open')) }}"></textarea>
                <div wire:ignore>
                    <x-laranail-emojis::picker :target="'[id=\'' . $this->getId() . '-input\']'" :locale="$locale" />
                </div>
            </div>
            BLADE;
    }
}
