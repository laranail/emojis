# Security and safety

`Emojis::sanitize()` returns a `Sanitizer` that removes hidden and reordering characters from untrusted text, then applies an optional emoji policy.

## Why

Text can carry content a person reviewing it cannot see, while software that reads it still does:

- **Emoji smuggling** — arbitrary bytes encoded as a run of variation selectors after one visible emoji.
- **Tag smuggling** — ASCII written in invisible Unicode tag characters, a known way to hide instructions
  from a human reviewer in text that is later passed to an LLM.
- **Trojan Source** — bidi overrides and isolates that make text display in a different order than it is
  stored, so what a reviewer reads is not what a parser gets.
- **Invisible fillers** — zero-width spaces and Hangul fillers that make two usernames look identical.
- **Zalgo** — combining-mark floods that overflow line boxes and break layouts.

## Usage

```php
use Simtabi\Laranail\Emojis\Facades\Emojis;

$clean = Emojis::sanitize($request->input('comment'))->clean();

$result = Emojis::sanitize($input)->run();
$result->text;               // the cleaned text
$result->report->toArray();  // ['tag' => 29, 'variation_selector' => 32] — counts only, never content
Emojis::sanitize($input)->isSafe();   // true when the security rules would remove nothing
```

## The security rules (always on)

| `Threat` | Removed |
|---|---|
| `InvalidUtf8` | invalid bytes are replaced before anything else runs |
| `Control` | C0/C1 controls except tab, line feed and carriage return |
| `Bidi` | embeddings, overrides, isolates and marks (U+202A–202E, U+2066–2069, U+200E, U+200F, U+061C); `keepBidiControls()` opts out |
| `Invisible` | U+200B, U+2060–2064, U+FEFF, U+180E, U+00AD, U+034F, U+2028, U+2029, U+2800, U+FFF9–FFFB, U+1D173–1D17A, U+17B4–17B5 and the Hangul fillers |
| `Tag` | tag characters outside the three RGI subdivision flags |
| `VariationSelector` | any selector not defined for the character before it — VS15/16 only after emoji-capable characters, VS1–14 only after `StandardizedVariants.txt` bases, VS17–256 only after a CJK ideograph — and any second selector |
| `Joiner` | ZWJ/ZWNJ that are not inside an emoji, between two emoji (at most four in a row), or between letters |
| `Combining` | combining marks past `maxCombiningMarks()` (default 4) on one character, and a mark repeating the one before it (UTS #39) |
| `OrphanComponent` | a skin tone, keycap mark, hair component or regional indicator with nothing to modify |
| `Oversized` | input past `input.max_bytes`: the text is cut at the limit and the report says so, so `isSafe()` is false and both validation rules fail it |

What stays: every emoji sequence the dataset knows (its joiners, selectors, tones and tags are inside it);
ZWJ and ZWNJ between letters, which Indic and Persian words need; one variation selector per character,
which keeps Japanese ideographic variation sequences (`葛󠄀`) intact; ordinary text in any language.

## Emoji policy (opt-in)

```php
use Simtabi\Laranail\Emojis\Core\Enums\{Group, EmojiVersion};
use Simtabi\Laranail\Emojis\Core\Security\EmojiPolicy;

$policy = EmojiPolicy::permissive()
    ->denyGroups(Group::Flags)
    ->deny('1F595')                       // skin-tone variants are denied with it
    ->supportedUpTo(EmojiVersion::V15_0)
    ->maxEmojis(10)
    ->replaceWith('');                     // default: remove

Emojis::sanitize($input)->policy($policy)->clean();
EmojiPolicy::only('1F44D', '1F44E', '2764-FE0F');   // an allow-list, e.g. for reactions
```

Set a default in `config('laranail.emojis.policy')` — see [configuration](../configuration.md).

## Validation

```php
use Simtabi\Laranail\Emojis\Laravel\Rules\{NoHiddenCharacters, EmojiPolicyRule};

'username' => ['required', new NoHiddenCharacters],          // reject instead of cleaning
'bio' => ['nullable', new EmojiPolicyRule],                  // the configured policy
'reaction' => ['required', new EmojiPolicyRule(EmojiPolicy::only('1F44D', '1F44E'))],
```

## Command line

```bash
php artisan laranail::emojis.sanitize - < message.txt > clean.txt    # report on STDERR
php artisan laranail::emojis.sanitize - --check < docs/page.md       # exit 1 if anything is hidden
```

## HTML output

Separately from sanitising: every HTML the package renders escapes surrounding text, and fitted images carry
no inline styles, so a strict Content-Security-Policy needs only `img-src` for the image host (plus `data:`
if you use embedded images of your own).

## Images you supply

Custom emoji, replacement images and anything passed to `Emojis::image()` or `EmojiImageRule` are checked for
size, encoding, real type and dimensions, and SVG is sanitised and only ever rendered inside `<img>`. URLs
must be `https://` or root-relative and are never fetched by the package, so there is no server-side request
forgery to defend against. See [images](images.md#your-own-images).

Images installed with `laranail::emojis.images install` are verified file by file against the SHA-256 this
package ships, so a changed or compromised CDN file is refused. See
[images](images.md#serving-from-your-own-origin).

---

[← Docs index](../../README.md#documentation)
