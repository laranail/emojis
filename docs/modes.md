# Modes and conversion

Thirteen `Mode` cases describe every form an emoji takes, and a converter reads some of them and writes any of them.

## The modes

| Mode | Example (`👋🏽`) | Read | Write |
|---|---|:-:|:-:|
| `Emoji` | `👋🏽` (fully-qualified, FE0F where required) | via `Unicode` | ✓ |
| `Text` | `☺︎` — VS15; defined for a few hundred characters only | via `Unicode` | ✓ |
| `Unicode` | the sequence as found in the input | ✓ | ✓ |
| `Ascii` | `:wave_tone3:` — always seven-bit | via `Shortcode` | ✓ |
| `Emoticon` | `:)` where one exists | ✓ | ✓ |
| `Shortcode` | `:wave_tone3:`, `:wave::skin-tone-4:` | ✓ | ✓ |
| `Image` | `<img … data-laranail-emoji="1F44B-1F3FD">` | this package's own markup | ✓ |
| `HtmlEntity` | `&#x1F44B;&#x1F3FD;` | ✓ | ✓ |
| `Escaped` | `\u{1F44B}\u{1F3FD}` and the JavaScript, Python and CSS forms | ✓ | ✓ |
| `Codepoint` | `U+1F44B U+1F3FD` | ✓ | ✓ |
| `Carrier` | a docomo, au, SoftBank or Google private-use code — see [Japanese emoji](tools/japanese.md) | ✓ | ✓ |
| `Name` | `[waving hand: medium skin tone]`, localized | — | ✓ |
| `Auto` | `Emoji` on a capable terminal, `output.auto_fallback` otherwise | — | ✓ |

Five modes are targets only, and passing one to `from()` throws `UnsupportedConversion`: `Emoji` and
`Text` are read through `Unicode`, `Ascii` is read back through `Shortcode`, a `Name` inside prose cannot be
found reliably, and `Auto` is a choice of target. The eight sources are `Unicode`, `Shortcode`,
`Emoticon`, `HtmlEntity`, `Escaped`, `Codepoint`, `Image` and `Carrier`.

## The matrix

Every readable form converts to every writable form. `tests/Unit/ConversionTest.php` runs all 90 cells on
one emoji, and `tests/Datasets` round-trips every emoji in the dataset through every shortcode preset,
entities, escapes, code points and ASCII.

## Degradation

Some targets cannot represent some emoji: most emoji have no text presentation, a few dozen have an
emoticon, and an image set may not publish every emoji. Rather than dropping the emoji, a converter walks a
fallback chain:

| Target | Default chain |
|---|---|
| `Text` | `Unicode` |
| `Emoticon` | `Shortcode` |
| `Image` | `Unicode` |
| `Carrier` | `Unicode` |
| `Emoji` (above a version cap) | decompose, then `Shortcode` |

Every chain ends in a form that always exists. Override per call or in config:

```php
Emojis::text('😀')->degrade(Mode::Text, Mode::Name)->toText();    // "[grinning face]"
```

Or refuse to degrade:

```php
Emojis::text('😀')->strict()->toText();                           // throws UnsupportedConversion
```

## Guarantees

- `Ascii`, `Shortcode`, `HtmlEntity`, `Escaped` and `Codepoint` output is seven-bit for every emoji,
  including Emoji 18 additions that no upstream shortcode set covers yet (they get a transliterated slug).
- Text *between* emoji is never changed, except that HTML output escapes it.
- Parsing accepts every qualification (`☺`, `☺️`) and normalises to the fully-qualified form; `normalize()`
  does only that.

---

[← Docs index](../README.md#documentation)
