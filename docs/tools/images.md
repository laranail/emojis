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

## Markup

```html
<img class="emoji" draggable="false" loading="lazy" decoding="async" alt="👋" aria-label="waving hand"
     title="waving hand" src="https://cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.3/assets/svg/1f44b.svg"
     data-laranail-emoji="1F44B">
```

`alt` is the character, so copying text keeps the emoji; `aria-label` and `title` carry the localized name.
`data-laranail-emoji` lets the markup be read back with `from(Mode::Image)`.

## Self-hosting

Copy a set to your own host and point at it:

```php
// config/laranail/emojis.php
'image_base_urls' => ['twemoji' => 'https://cdn.example.com/twemoji/svg'],
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
