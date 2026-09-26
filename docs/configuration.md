# Configuration

Every key in `config/laranail/emojis.php`, its default, and what it changes.

Publish with `php artisan vendor:publish --tag=laranail::emojis-config`. Outside Laravel, pass the same keys
to `Emojis::create([...])`. Every value is a plain scalar or list, so `php artisan config:cache` works.

## Keys

| Key | Default | Meaning |
|---|---|---|
| `locale` | `null` | `null` follows the application locale on every call; a tag (`'fr'`, `'pt-BR'`) pins one. |
| `fallback_locale` | `'en'` | Used when the requested locale is not shipped. |
| `shortcode_preset` | `'github'` | Output vocabulary for `Mode::Shortcode`: `github`, `emojibase`, `slack`, `joypixels`, `cldr`. Parsing accepts all of them. |
| `shortcode_delimiters` | `[':', ':']` | Wrapped around shortcode and ASCII output. |
| `image_set` | `'twemoji'` | `twemoji`, `noto`, `openmoji`, `fluent`, `joypixels`, or a set you registered. Checked at boot. |
| `image_base_urls` | `[]` | Per-set base URL, to serve images from your own host. |
| `image_class` | `'emoji'` | The `class` on every `<img>`. |
| `name_template` | `'[{name}]'` | `Mode::Name` output; must contain `{name}`. |
| `degradation` | `[]` | Per-target fallback chains, e.g. `'text' => ['shortcode', 'ascii']`. |
| `auto_fallback` | `'ascii'` | What `Mode::Auto` becomes when the terminal cannot draw emoji. |
| `max_input_bytes` | `1048576` | Larger input throws `InvalidInput` instead of pinning a worker. |
| `custom` | `[]` | Image-only custom emoji: `'laravel' => ['url' => …, 'aliases' => [], 'fallback' => null, 'label' => …]`. |
| `shortcodes` | `[]` | Extra shortcodes for existing emoji: `'shipit' => '1F680'`. |
| `emoticons` | `[]` | Extra emoticons: `':3' => '1F63A'`. |

## Environment

| Variable | Effect |
|---|---|
| `LARANAIL_EMOJIS` | `1` or `0` forces terminal emoji support on or off, overriding detection. |

## Validation at boot

An `image_set` that does not exist, or a `custom` entry with an unsafe URL or a name that shadows a Unicode
shortcode, stops the application at boot. These are configuration errors: continuing would render every
emoji through a fallback while reporting success, so they fail where they are caused rather than on the
first page view.

## Freezing

`custom`, `shortcodes` and `emoticons` — and anything registered in a service provider through
`Emojis::addCustom()`, `addShortcode()`, `addEmoticon()` or `addImageSet()` — are frozen once the
application has booted. A registration during a request throws. That is what makes one `Emojis` instance
per worker safe under Octane.

---

[← Docs index](../README.md#documentation)
