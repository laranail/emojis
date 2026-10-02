# Add an emoji picker to a form

Give a message box an emoji button that inserts at the caret.

```blade
{{-- in the layout's <head>, once --}}
<x-laranail-emojis::styles picker />

<textarea id="message" name="message"></textarea>
<x-laranail-emojis::picker target="#message" />

{{-- before </body>, once --}}
<x-laranail-emojis::scripts />
```

With Livewire, either point the same picker at a `wire:model` field or use
`<livewire:laranail-emojis.picker wire:model="message" />`. Enable the [HTTP API](../tools/api.md) to load
the emoji from a cacheable URL instead of embedding them in the page. Every option is in
[emoji picker](../tools/picker.md).

---

[← Docs index](../../README.md#documentation)
