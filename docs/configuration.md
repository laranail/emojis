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
| `images.class` | `'emoji'` | The `class` on every image. |
| `images.base_urls` | `[]` | Per-set base URL, to serve images from your own host. |
| `output.name_template` | `'[{name}]'` | `Mode::Name` output; must contain `{name}`. |
| `output.auto_fallback` | `'ascii'` | What `Mode::Auto` becomes when the terminal cannot draw emoji. |
| `output.degradation` | `[]` | Per-target fallback chains, e.g. `'text' => ['shortcode', 'ascii']`. |
| `input.max_bytes` | `1048576` | Larger input throws `InvalidInput` instead of pinning a worker. |
| `extend.custom` | `[]` | Image-only custom emoji: `'laravel' => ['url' => …, 'aliases' => [], 'fallback' => null, 'label' => …]`. |
| `extend.shortcodes` | `[]` | Extra shortcodes for existing emoji: `'shipit' => '1F680'`. |
| `extend.emoticons` | `[]` | Extra emoticons: `':3' => '1F63A'`. |
| `policy` | `[]` | Default emoji policy for `sanitize()` and `EmojiPolicyRule`: `allow_groups`, `deny_groups`, `deny_subgroups`, `allow_only`, `deny`, `max_version`, `allow_unknown`, `allow_custom`, `max_emojis`, `replacement` — see [security](tools/security.md#emoji-policy-opt-in). |

Outside Laravel, `Emojis::create()` takes the same array:

```php
Emojis::create(['locale' => ['default' => 'fr'], 'images' => ['set' => 'noto', 'fit' => 'tight']]);
```

## Upgrading from 0.1.0

0.1.0 used flat keys (`image_set`, `shortcode_preset`, `max_input_bytes`, a string `locale`, extra shortcodes
under a top-level `shortcodes`). A config still in that layout stops boot with an `InvalidArgumentException`
that names each old key and where it moved, rather than being read as defaults. Republish it:

```bash
php artisan vendor:publish --tag=laranail::emojis-config --force
```

## Environment

| Variable | Effect |
|---|---|
| `LARANAIL_EMOJIS` | `1` or `0` forces terminal emoji support on or off, overriding detection. |

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
