# Render emoji as images in Blade

Show consistent emoji images across platforms while keeping the text escaped and the emoji copyable.

```blade
{{-- a user's comment: text escaped, emoji and shortcodes as Twemoji images --}}
<p>@laranailEmojis($comment->body)</p>

{{-- one emoji --}}
<x-laranail-emojis::emoji name="tada" mode="image" />
```

Include `<x-laranail-emojis::styles />` once in your layout's `<head>` to size them to the text; to change
the size, set `--laranail-emoji-image-size` (see [styles](../tools/styles.md#theming)).

Pick another set with `config('laranail.emojis.images.set')`, and credit Twemoji or OpenMoji on your site —
see [images](../tools/images.md#attribution).

---

[← Docs index](../../README.md#documentation)
