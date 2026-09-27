# Fit emoji into UI without padding

Make image emoji sit in text and fill buttons without the empty border their sets draw around them.

```blade
<head>
    <x-laranail-emojis::styles />
</head>

<p>@laranailEmojis($message->body)</p>                                  {{-- Fit::Balanced by default --}}

<button class="laranail-emoji-box" style="--laranail-emoji-size: 1.75rem">
    <x-laranail-emojis::emoji name="thumbsup" mode="image" fit="tight" />
</button>
```

`balanced` removes each set's safe-area border while keeping emoji at consistent sizes; `tight` fills the
box with the artwork. See [images](../tools/images.md#fit).

---

[← Docs index](../../README.md#documentation)
