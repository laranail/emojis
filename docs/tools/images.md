# Images

Five built-in image sets, addressed by URL; nothing is bundled.

## Sets

| Set | Licence | Notes |
|---|---|---|
| `twemoji` (default) | graphics CC-BY 4.0 — attribution required | jdecked/twemoji 17.0.3; no Emoji 18 glyphs yet |
| `noto` | Apache-2.0 | Google Noto 2D; no country flags |
| `openmoji` | CC BY-SA 4.0 — attribution and share-alike | |
| `fluent` | MIT | Microsoft Fluent Emoji, colour style; no flags |
| `joypixels` | JoyPixels Free License — **personal use only** | commercial use needs a paid licence |

Every URL is pinned to the release the coverage data was built from, never `@latest`, so an image the
dataset says exists does exist. An emoji a set does not publish renders through the fallback chain —
by default as the native character — rather than as a broken image.

## Fit

Image sets draw inside a safe area. Measured on every pinned image (the border is the smallest of an image's
four margins, as a share of its width):

| Set | Images measured | Median border | A grinning face |
|---|---:|---:|---:|
| Twemoji | 3,952 | 0% | 0% |
| Noto | 3,708 | 3.1% | 4.4% |
| Fluent | 3,016 | 6.0% | 6.2% |
| OpenMoji | 3,953 | 9.5% | 16.6% |

That border is what makes emoji look too small or oddly spaced next to text and in buttons. `Fit`
removes it, using crops derived from measuring every image, so no crop ever cuts into artwork:

| `Fit` | Result |
|---|---|
| `Balanced` (default) | the set's common border removed; every emoji keeps its relative size, so a small symbol stays small |
| `Tight` | each emoji cropped to its own artwork — maximum fill for reactions, avatars and icon buttons |
| `None` | the published image as a plain `<img>` |

```php
Emojis::text('Ship it 🚀')->fit(Fit::Tight)->toImages();
```

```blade
<x-laranail-emojis::emoji name="rocket" mode="image" fit="tight" />
```

Fitted output is an inline `<svg>` whose `viewBox` is the crop, wrapping the image:

```html
<svg class="emoji" viewBox="95 95 810 810" width="1em" height="1em" role="img" aria-label="grinning face"
     data-laranail-emoji="1F600" data-laranail-fit="balanced"><title>grinning face</title>
  <image href="https://cdn.jsdelivr.net/npm/openmoji@17.0.0/color/svg/1F600.svg" width="1000" height="1000"/></svg>
```

The crop is in the markup, so it needs no inline style and no per-emoji CSS: a strict Content-Security-Policy
only has to allow the image host in `img-src`. When a crop would remove nothing (Twemoji, whose median border
is 0), a plain `<img>` is rendered instead. Custom sets are not measured and render as `<img>`, as do two Noto
images the rasteriser cannot read (🏳️‍🌈 and 📦).

How the crops are made: `tools/measure` rasterises every image with resvg at 512 px and records each
transparent margin, rounded down, in `database/sources/measured/bounds/`; the dataset generator turns those into
crops. `Balanced` insets every emoji by the set's median border, capped at that emoji's own smallest margin,
so no crop cuts into artwork and emoji keep their relative sizes. `Tight` is the square around the artwork.

## Layout

Include the stylesheet once — `<x-laranail-emojis::styles />` in your layout's `<head>` (it passes Laravel's
Vite CSP nonce, or `:nonce="$nonce"`), link the published file, or `@use` the SCSS in your own build; see
[styles](styles.md). It sizes images to the
text, fixes the tall line box and side bearings of native colour-emoji fonts (`.laranail-emoji`), and adds
`.laranail-emoji-box` for square containers:

```html
<span class="laranail-emoji-box" style="--laranail-emoji-size: 2rem">…</span>
```

## Markup

```html
<img class="emoji" draggable="false" loading="lazy" decoding="async" alt="👋" aria-label="waving hand"
     title="waving hand" src="https://cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.3/assets/svg/1f44b.svg"
     data-laranail-emoji="1F44B">
```

For `<img>`, `alt` is the character, so copying text keeps the emoji; `aria-label` and `title` carry the
localized name (for fitted `<svg>`, `aria-label` and `<title>`).
`data-laranail-emoji` lets the markup be read back with `from(Mode::Image)`.

## Serving from your own origin

A CDN sees every visitor's IP address, which may matter under GDPR, and is one more host for your
Content-Security-Policy to allow. Install a verified copy of a set into the application instead:

```bash
php artisan laranail::emojis.images install twemoji   # into public/vendor/laranail/emojis/images/twemoji
php artisan laranail::emojis.images verify twemoji    # exit 1 if any file changed since; for deploy checks
```

```php
// config/laranail/emojis.php
'images' => ['set' => 'twemoji', 'source' => 'local'],
```

Nothing is bundled in the package — the four sets are 9.8 MB (Twemoji), 12.6 MB (OpenMoji), 40.7 MB (Noto)
and 122 MB (Fluent) — so `install` downloads the set once. Every file must match the SHA-256 this package
ships for the pinned version, or it is refused and nothing is written for it; accepted files are sanitised
(below) and written atomically. A manifest records what was written, which is what `verify` checks.
JoyPixels cannot be installed: its licence does not allow redistribution.

With `source` set to `local`, only sets that have been installed are served locally; any other keeps its CDN
address, and boot records a degraded warning (visible in `laranail::package-tools.doctor`) until you
install it.

To use your own mirror instead, set a base URL per set:

```php
'images' => ['base_urls' => ['twemoji' => 'https://cdn.example.com/twemoji/svg']],
```

## Your own images

Give one emoji your own picture, or add an emoji Unicode does not have, from a URL, a data URI, base64, raw
bytes or a file — PNG, GIF, JPEG, WebP or SVG:

```php
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;

Emojis::useImage('thumbsup', 'https://brand.example/thumbs.png');           // replaces 👍 in Image mode
Emojis::addCustom('laravel', 'data:image/svg+xml;base64,PHN2ZyB4bWxu…');    // :laravel:
Emojis::addCustom('logo', EmojiImage::fromFile(resource_path('logo.png')));
Emojis::addCustom('party', EmojiImage::fromBase64($base64, 'image/webp'));

$image = Emojis::image($request->input('emoji'));   // validate without registering; throws InvalidImage
```

Every image goes through the same checks, configured under `images.custom`:

| Check | Rule |
|---|---|
| Size | at most `max_bytes` (256 KB) decoded, refused before decoding |
| Encoding | strict base64; anything outside the alphabet is refused |
| Type | sniffed from the content; a declared type (data URI, argument) must agree with it. PNG, GIF, JPEG, WebP, SVG only |
| Dimensions | read from the header, never by decoding pixels, at most `max_dimension` (1024 px), so a decompression bomb is refused cheaply |
| SVG | sanitised (below), or refused entirely with `'svg' => false` |
| URL | `https://` or root-relative only; no credentials, whitespace, quotes, backslashes or `//`; optionally only `hosts`. Never fetched by the package |

Embedded images are re-encoded as a base64 data URI, so the bytes that render are the bytes that were
checked. The exception message names the rule that failed and never contains the input.

### SVG sanitising

An SVG is rebuilt from an allow-list rather than filtered: static shapes, gradients, clip paths, masks and
filters in the SVG namespace, with presentation attributes. Scripts, event handlers, `<foreignObject>`,
`<style>`, `<image>`, links, animation, other namespaces, comments and processing instructions never reach
the output. References must stay inside the document (`href="#id"`, `url(#id)`); a DOCTYPE with entity
declarations is refused (XXE, entity expansion); element count and nesting depth are capped.

It is the second defence: SVG is only ever rendered inside `<img src="data:…">`, where browsers run no
script and load nothing external. The allow-list was derived from all 14,631 files of the four pinned sets.
Rasterised before and after sanitising, 14,629 are pixel-identical; the other two (Noto's rainbow flag and
package) crash the rasteriser, resvg, in their original form too, so they could not be compared.

## Your own set

```php
use Simtabi\Laranail\Emojis\Core\Render\ImageSets\TemplateImageSet;

Emojis::addImageSet(new TemplateImageSet('brand', 'https://img.example.com/emoji/{hex_lower}.png'));
```

Placeholders: `{hex}`, `{hex_lower}`, `{hex_nofe0f}` (the Twemoji rule), `{noto}`, `{slug}`. For anything
else, implement `Simtabi\Laranail\Emojis\Core\Contracts\ImageSet`. Register sets in a service provider;
registrations are frozen after boot.

## Attribution

If you use Twemoji or OpenMoji output on a public page, their licences require attribution, and OpenMoji's
requires derivatives to be shared alike. The package cannot do that for you: add it to your footer or
credits page.

---

[← Docs index](../../README.md#documentation)
