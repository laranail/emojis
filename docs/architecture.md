# Architecture

A framework-free core that owns the catalogue, the scanner and every conversion, and a thin Laravel shell over it.

## Layout

| Path | What lives there |
|---|---|
| `src/Core/` | Everything that knows about emoji. No Illuminate, no Symfony, no other laranail package; `psr/log` only. |
| `src/Core/Data/` | `DatasetStore` (the one place untyped data is loaded and validated) and `Record` (the record layout). |
| `src/Core/Catalogue/` | Lookups and hydration (`Catalogue`), `Query`, `EmojiCollection`. |
| `src/Core/Text/` | `Scanner`, `TextConverter`, `HtmlSegments`, `Token`, `EmojiMatch`. |
| `src/Core/Render/` | `Renderer` (one emoji into one mode, with degradation) and the image sets. |
| `src/Core/Locale/`, `Terminal/`, `Extension/` | CLDR names, terminal detection, custom emoji. |
| `src/Providers/`, `src/Laravel/`, `src/Facades/`, `src/Console/` | The Laravel shell. |
| `database/generated/` | The shipped dataset: generated PHP shards and `VERSION` — never edit by hand. |
| `database/sources/` | Generator inputs, not shipped: `upstream.lock.json`, `curated/` JSON, `measured/` image bounds. |
| `resources/assets/` | Front-end source: `styles/*.scss` (and `scripts/`), built by Vite. |
| `public/assets/` | The committed build: `css/emojis.css`. Read by `Emojis::stylesheet()`, published to `public/vendor/laranail/emojis`. |
| `resources/lang/` | Translations. |
| `tools/` | The generators, the source lock, and the gates. |

The boundary is enforced three ways: deptrac statically (`tools/deptrac-guard.php`), a Pest arch test, and
`tools/core-isolation.php`, which runs the core under an autoloader that refuses every class outside it.

## Why no regex over the emoji set?

The obvious scanner — one alternation of every emoji sequence, or `\p{Extended_Pictographic}` — does not
survive contact with real PHP builds:

- An alternation of the ~5,200 sequences exceeds PCRE's compiled-pattern limit on builds using 2-byte
  links, even when factored into a trie.
- PCRE2 gained emoji properties in 10.40. PHP builds still link 10.36 (MAMP's 8.4 and 8.5 among them),
  where every emoji property fails to compile; newer builds lag Unicode by a version or two.
- `grapheme_str_split` on ICU 57 splits modern ZWJ sequences into several pieces.

So the scanner uses a generated character class of every code point that can start a sequence to find
candidates, then probes a generated hash table at each candidate for every distinct sequence byte length,
longest first. That is `isset()` in the inner loop, independent of JIT, and gives the same answer
everywhere. Emoji clusters are counted from the scanner's tokens, not from ICU. A separate generated class
of `Extended_Pictographic` ranges — including Unicode's reserved future blocks — finds emoji the dataset
does not know, for `strip()` and the validation rules.

## Why a generated dataset?

The data comes from pinned, checksummed upstream files (Unicode, CLDR, emojibase, gemoji, Google's
emoji metadata, iamcal), merged with the curated inputs in `database/sources/curated/`, and is emitted as PHP into
`database/generated/`. PHP files are cached by
opcache as immutable arrays, so loading the catalogue costs a pointer copy; nothing is fetched at runtime.
The generator is the only writer, `--check` byte-compares in CI, and every count it asserts is read from the
source's own footer. See [data sources](tools/data-sources.md).

## Failure handling

Failures are classified by what continuing would leave behind, never by environment:

| Failure | Class | Behaviour |
|---|---|---|
| A core shard is missing, unreadable, or has an unexpected record layout | critical | `DatasetException` |
| The configured image set does not exist; a custom emoji is unsafe | critical | thrown at boot |
| A caller asks for an emoji, image set or skin tone that does not exist | caller error | `EmojiNotFound`, `ImageSetNotFound` |
| A shipped locale shard fails to load | degradable | English names; reported once and recorded (`reporter()->degradations()`, doctor) |
| A requested locale is not shipped | tolerated | fallback chain; logged once as a warning |
| Input is not UTF-8 or is over `input.max_bytes` | caller error | `InvalidInput`, without echoing the input |

No exception message or log context ever includes the caller's text. In Laravel, degradable failures go
through `laranail/package-tools`' `FailurePolicy`, so they reach the exception handler and `BootReport`.

## Why one instance per worker?

`Emojis` is a container singleton. It holds only data derived from the immutable dataset and from
registrations that are frozen once the application has booted; the locale is read from the application on
every call. Under Octane nothing a request does can change what the next request sees.

---

[← Docs index](../README.md#documentation)
