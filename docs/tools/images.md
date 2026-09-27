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

## Self-hosting

Copy a set to your own host and point at it:

```php
// config/laranail/emojis.php
'images' => ['base_urls' => ['twemoji' => 'https://cdn.example.com/twemoji/svg']],
```

Third-party CDNs see your visitors' IP addresses; self-hosting avoids that, which may matter under GDPR.

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
