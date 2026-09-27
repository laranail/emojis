# Text conversion

`TextConverter` (from `Emojis::text()` or `Emojis::html()`) finds every emoji in a piece of text and writes it in one target mode.

Converters are immutable: each option returns a new converter, so a configured one can be reused.

## Reading

| Method | Effect |
|---|---|
| `from(Mode ...)` | the forms to parse; default `Unicode` and `Shortcode` |
| `alsoFrom(Mode ...)` | add forms, keeping the current ones |
| `withEmoticons(bool $risky = false)` | parse ASCII emoticons, word-bounded |
| `includeTextPresentation()` | also treat `©`, `®`, `™`, `☺` without FE0F as emoji |

Emoticons match only as whole words — preceded by the start or whitespace, followed by the end, whitespace
or closing punctuation — so `http://`, `foo();)` and `x<3` are left alone. Emoticons starting with a letter
or digit (`XD`, `D:`, `8)`, `B-)`) are "risky" and match only with `withEmoticons(risky: true)`.

Shortcodes must not be glued to a word, a path or a stray colon: `12:30:45`, `std::vector`,
`laranail::emojis.search` and `/users/:id:` stay as they are, while `:smile::smile:` is two emoji.
Slack's two-token tones (`:wave::skin-tone-4:`) are understood.

## Writing

| Method | Effect |
|---|---|
| `to(Mode, ?EscapeFormat)` | the conversion; `Image` output and HTML input produce HTML |
| `toHtml(Mode = Image)` | HTML-safe `Stringable` (`HtmlString` in Laravel): plain text escaped |
| `toEmoji()`, `toText()`, `toUnicode()`, `toAscii()`, `toShortcodes()`, `toEmoticons()`, `toImages()`, `toHtmlEntities()`, `toEscaped()`, `toCodepoints()`, `toNames()` | shortcuts |
| `preset(ShortcodePreset)`, `locale()`, `imageSet()` | per-call choices |
| `skinTone(?SkinTone)` | apply a tone to every single-person emoji that has none |
| `supportedUpTo(EmojiVersion)` | render as an older platform would |
| `degrade(Mode $target, Mode ...$chain)`, `strict()` | fallback control — see [modes](../modes.md#degradation) |
| `through(Closure $stage)` | post-process each rendered emoji: `fn (string $out, ?Emoji $e, ?CustomEmoji $c): string` |
| `replace(Closure)` | replace each emoji with the callback's result |
| `normalize()` | rewrite every emoji in its fully-qualified form |

## Inspecting

| Method | Returns |
|---|---|
| `extract(bool $includeUnknown = false)` | `list<EmojiMatch>`: byte `offset`, `length`, `text`, `source`, `emoji`; `utf16Offset()` for JavaScript |
| `emojis()` | `EmojiCollection` of the known emoji found, in order |
| `count(bool $includeUnknown = false)`, `contains(bool $includeUnknown = true)` | |
| `isOnlyEmoji()` | one or more emoji and nothing but whitespace |
| `strip(bool $collapseWhitespace = false)` | the text without any emoji, known or not |
| `length()`, `width()`, `truncate(int $width, string $ellipsis = '…')` | grapheme-safe, one character and two columns per emoji |

"Unknown" means a pictograph the dataset does not know: an emoji from a newer Unicode version, a vendor
sequence such as `🐱‍👤`, or a stray modifier or variation selector.

## HTML input

`Emojis::html($html)` converts text runs only. Markup, attributes, comments, and everything inside `code`,
`pre`, `kbd`, `samp`, `script`, `style`, `textarea` and `template` are returned byte-identical. Text is
decoded before scanning (so `&lt;3` is an emoticon) and re-escaped where it changed.

## Limits

Input must be valid UTF-8 and at most `input.max_bytes`; otherwise `InvalidInput` is thrown with a message
that never contains the input.

---

[← Docs index](../../README.md#documentation)
