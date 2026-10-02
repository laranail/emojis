# Configuration

Every key in `config/laranail/emojis.php`, its default, and what it changes.

Publish with `php artisan vendor:publish --tag=laranail::emojis-config`. Outside Laravel, pass the same keys
to `Emojis::create([...])`. Every value is a plain scalar or list, so `php artisan config:cache` works.

## Keys

Grouped by concern; each group's keys are listed once.

| Key | Default | Meaning |
|---|---|---|
| `locale.default` | `null` | `null` follows the application locale on every call; a tag (`'fr'`, `'pt-BR'`) pins one. |
| `locale.fallback` | `'en'` | Used when the requested locale is not shipped. |
| `shortcodes.preset` | `'github'` | Output vocabulary for `Mode::Shortcode`: `github`, `emojibase`, `slack`, `joypixels`, `cldr`. All are parsed. |
| `shortcodes.delimiters` | `[':', ':']` | Wrapped around shortcode and ASCII output. |
| `images.set` | `'twemoji'` | `twemoji`, `noto`, `openmoji`, `fluent`, `joypixels`, or a registered set. Checked at boot. |
| `images.fit` | `'balanced'` | `balanced`, `tight` or `none` — see [images](tools/images.md#fit). |
| `images.class` | `''` | Extra classes on every image, after the package's own `laranail-emoji laranail-emoji-image`, which the stylesheet targets. |
| `images.source` | `'cdn'` | `cdn`, or `local` to serve installed sets from `public/vendor/laranail/emojis/images` — see [images](tools/images.md#serving-from-your-own-origin). |
| `images.base_urls` | `[]` | Per-set base URL, to serve images from your own mirror. |
| `images.custom.max_bytes` | `262144` | Largest image you supply, decoded. |
| `images.custom.max_dimension` | `1024` | Widest or tallest raster, in pixels, read from the header. |
| `images.custom.svg` | `true` | Accept SVG (sanitised). `false` refuses it. |
| `images.custom.max_svg_elements` | `20000` | Elements an SVG may hold before it is refused. |
| `images.custom.urls` | `true` | Accept image URLs. `false` accepts only inline images (data URI, base64, file) — see [security](tools/security.md#images-you-supply). |
| `images.custom.hosts` | `[]` | Hosts an image URL may name; empty allows any `https://` host. |
| `output.name_template` | `'[{name}]'` | `Mode::Name` output; must contain `{name}`. |
| `output.tag_template` | `'[{tag}]'` | `Mode::Tag` output; must contain `{tag}`. See [status tags](tools/tags.md). |
| `output.auto_fallback` | `'ascii'` | What `Mode::Auto` becomes when the terminal cannot draw emoji. |
| `output.degradation` | `[]` | Per-target fallback chains, e.g. `'text' => ['shortcode', 'ascii']`. |
| `input.max_bytes` | `1048576` | Larger input throws `InvalidInput` instead of pinning a worker. |
| `input.disabled_emoticons` | `[]` | Emoticons never to match, list or write: `[':P', '^^']` ([emoticons](tools/emoticons.md#choosing-what-matches)). |
| `extend.custom` | `[]` | Image-only custom emoji: `'laravel' => ['image' => 'https://… or data:image/…', 'aliases' => [], 'fallback' => null, 'label' => …]`. |
| `extend.images` | `[]` | Your image for an existing emoji: `'thumbsup' => 'https://…'` — see [images](tools/images.md#your-own-images). |
| `extend.shortcodes` | `[]` | Extra shortcodes for existing emoji: `'shipit' => '1F680'`. |
| `extend.emoticons` | `[]` | Extra or remapped emoticons, which always match: `':X' => '1F910'`. |
| `policy` | `[]` | Default emoji policy for `sanitize()` and `EmojiPolicyRule`: `allow_groups`, `deny_groups`, `deny_subgroups`, `allow_only`, `deny`, `max_version`, `allow_unknown`, `allow_custom`, `max_emojis`, `replacement` — see [security](tools/security.md#emoji-policy-opt-in). The HTTP API and the picker payload apply it too. |
| `api.enabled` | `false` | Registers the read-only [HTTP API](tools/api.md); off means no routes at all. |
| `api.prefix`, `api.version` | `'laranail/emojis/api'`, `'v1'` | Where it is served. |
| `api.middleware` | `['api', 'throttle:120,1']` | Its middleware; put authentication here. |
| `api.cache_headers` | `'public;max_age=3600;etag'` | Laravel's `cache.headers` value; `''` for none. |

Outside Laravel, `Emojis::create()` takes the same array:

```php
Emojis::create(['locale' => ['default' => 'fr'], 'images' => ['set' => 'noto', 'fit' => 'tight']]);
```

## Upgrading

Each step lists what can change your output or break your code. The [changelog](../CHANGELOG.md) has the rest.

### From 0.1.0

0.1.0 used flat keys (`image_set`, `shortcode_preset`, `max_input_bytes`, a string `locale`, extra shortcodes
under a top-level `shortcodes`). A config still in that layout stops boot with an `InvalidArgumentException`
that names each old key and where it moved, rather than being read as defaults. Republish it:

```bash
php artisan vendor:publish --tag=laranail::emojis-config --force
```

### From 0.1 to 0.2

- `extend.custom.<name>.url` is now `image`, and also takes a data URI. The old key stops boot with a message
  saying so.
- Custom-emoji images are validated as images: a data URI must really be the type it declares, within the
  size and dimension limits, and SVG is sanitised. `Emojis::addCustom()` throws `InvalidImage` (was
  `InvalidCustomEmoji`) for a refused image, and `CustomEmoji::$imageUrl` is now `CustomEmoji::$image`, an
  `EmojiImage`.
- Registering a custom emoji whose name or alias is already taken throws instead of silently replacing it.
- Every class is prefixed: images carry `laranail-emoji laranail-emoji-image` (was `emoji`) and the Blade
  component's span `laranail-emoji laranail-emoji-native` (was `laranail-emoji`). `images.class` now adds
  classes instead of replacing them, so a published config still holding `'class' => 'emoji'` keeps working
  and adds `emoji` as an extra hook. Update your own CSS that targeted `.emoji`.
- `AsEmojiText` writes an escaped format (a typed `:` or `\` is escaped). Rows written by 0.1 read back as
  before, except that a shortcode touching a letter is now converted too.

### From 0.2 to 0.3

- `(y)`, `(n)`, `:?` and `<><` match only with `withEmoticons(risky: true)`, because they read as prose.
  `addEmoticon('(y)', 'thumbs up')` turns one back on alone.
- An emoticon added with `addEmoticon()` always matches, even where the dataset marks the same text opt-in.
- `database/generated/symbols.php` holds characters rather than code points. Read symbols through
  `Emojis::symbols()`; the shards are not a stable interface.

### From 0.3 to 0.4

- `addShortcode()` remaps an existing code for writing as well as reading, so `toShortcodes()` no longer
  writes a code that would read back as another emoji.
- `Emojis::create()` applies a config array's `extend.*` and `input.disabled_emoticons`, as the Laravel
  provider always did. An array that carried them and relied on their being ignored now registers them.
- The scanner reads the configured `shortcodes.delimiters`, not only `:`.
- `Emoji::toImage()` and the Blade component label in the locale of the call (the application locale in
  Laravel), not the configured default. The component takes `locale` and `set` in every mode, and an
  unknown `mode` or `fit` throws an `InvalidArgumentException` naming the accepted values.
- `Symbols::search($query, limit: 0)` returns every match instead of none, as `search()` and
  `searchKaomoji()` do.
- `html()` leaves `title`, `xmp`, `iframe`, `noembed`, `noframes`, `noscript` and `plaintext` alone, as it
  already did `script`, `style` and `textarea`.
- The sanitiser also removes U+206A–206F, U+1BCA0–1BCA3, and Mongolian free variation selectors that do
  not follow a Mongolian letter.
- `Mode` has a fourteenth case, `Tag`. A `match` over `Mode` in your code with no `default` arm needs a `Tag`
  arm, or it throws `UnhandledMatchError` when it meets one.

## Environment

| Variable | Effect |
|---|---|
| `LARANAIL_EMOJIS` | `1` or `0` forces terminal emoji support on or off, overriding detection. |
| `LARANAIL_EMOJIS_API` | `true` enables the [HTTP API](tools/api.md) (`api.enabled`). |

## Validation at boot

An `images.set` that does not exist, or an `extend.custom` entry with an unsafe URL or a name that shadows a Unicode
shortcode, stops the application at boot. These are configuration errors: continuing would render every
emoji through a fallback while reporting success, so they fail where they are caused rather than on the
first page view.

## Freezing

The `extend` entries — and anything registered in a service provider through
`Emojis::addCustom()`, `addShortcode()`, `addEmoticon()` or `addImageSet()` — are frozen once the
application has booted. A registration during a request throws. That is what makes one `Emojis` instance
per worker safe under Octane.

---

[← Docs index](../README.md#documentation)
