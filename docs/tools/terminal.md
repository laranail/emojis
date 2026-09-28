# Terminal output

`Mode::Auto` writes emoji where the terminal can draw them and a seven-bit fallback where it cannot.

## Detection

`EnvTerminalProbe` follows the rules of `is-unicode-supported`, the de facto reference:

| Environment | Emoji |
|---|---|
| `LARANAIL_EMOJIS=1` / `0` | forced on / off |
| `TERM=dumb`, `TERM=linux` (the kernel console) | no |
| Windows Terminal (`WT_SESSION`), VS Code, JetBrains, Cmder, Alacritty, rxvt-unicode | yes |
| Legacy Windows console host | no — its font has no emoji, whatever the code page |
| macOS Terminal, iTerm, WezTerm, Ghostty | yes |
| otherwise | yes when `LC_ALL`, `LC_CTYPE` or `LANG` is UTF-8 |

`NO_COLOR` is not consulted: it turns off colour, not glyphs. Flags show as two letters on Windows even in
Windows Terminal, and `supportsFlags()` reports that.

In Laravel the probe also defers to `laranail/console`'s `Capabilities`, so one setting and one test fake
(`Capabilities::fake()`) govern every laranail command.

When the terminal cannot draw emoji, `Mode::Auto` becomes `output.auto_fallback` — `ascii` by default, so output
stays readable in any log file.

## laranail/console

Console discovers this package at runtime through its `EmojiCatalogue` contract. Console never requires
emojis; emojis ships the adapter, `Laravel\ConsoleEmojiCatalogue`, and console finds it by name. Once both are
installed:

- `Console::emoji()` and every console widget resolve any shortcode in the catalogue;
- `ConsoleUIFormatter::message()` and `icon()` do the same (console 0.1.4 and later), resolving when the string
  is rendered so a later `capabilities()` call still applies;
- console's `DisplayWidth` measures emoji sequences exactly as `->width()` does here.

`tests/Feature/ConsoleCatalogueTest.php` pins each of these against the real classes.

## Width and truncation

Terminals give an emoji sequence two columns, but `mb_strwidth()` counts code points: the family emoji
`👨‍👩‍👧‍👦` measures 11. Use the converter instead:

```php
Emojis::text('Deploy 🚀 done')->width();          // 14
Emojis::text($message)->truncate(40);            // never splits a sequence
```

Each emoji sequence is one character and two columns, whatever the ICU version.

---

[← Docs index](../../README.md#documentation)
