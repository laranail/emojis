# Styles

One stylesheet sizes emoji to the surrounding text. Its source is SCSS in `resources/assets/styles/`, Vite
builds it into `public/assets/css/emojis.css`, and Laravel publishes that file to
`public/vendor/laranail/emojis/`.

## What it does

Every class carries the `laranail-emoji` prefix, so the stylesheet cannot restyle anything of your
application's, and none of its rules match an unprefixed name.

| Class | Applies to | Effect |
|---|---|---|
| `.laranail-emoji` | every emoji the package renders | An inline box with a `1` line box that a flex parent cannot squeeze |
| `.laranail-emoji-image` | the `<img>` or fitted `<svg>` | Sizes the image to the text, `1.15em` square, on the baseline |
| `.laranail-emoji-native` | the Blade component's `<span>` around a native emoji | Pins the colour-emoji font and presentation, whose side bearings and tall line box otherwise push text apart |
| `.laranail-emoji-box` | a container you add: reactions, avatars, pickers | A square the emoji fills edge to edge |

Rendered images carry `class="laranail-emoji laranail-emoji-image"`, followed by anything in `images.class`;
the Blade component's span carries `class="laranail-emoji laranail-emoji-native"`.

## Including it

Pick one per layout.

| Way | Markup | When |
|---|---|---|
| Inline | `<x-laranail-emojis::styles />` | Default. No publish step, and it works under a strict CSP: Laravel's Vite nonce is added automatically, or pass `:nonce="$nonce"`. |
| Link | `<x-laranail-emojis::styles link />` | A cacheable file. Publish it first (below). |
| Your build | `@use 'vendor/laranail/emojis/resources/assets/styles/emojis';` | You compile your own SCSS and want to override the tokens. |

Outside Laravel, `Emojis::stylesheet()` returns the built CSS as a string, and `Emojis::assetPath('css/emojis.css')`
gives its path on disk.

## Publishing

```bash
php artisan vendor:publish --tag=laranail::emojis-assets
```

The whole of `public/assets` is copied to `public/vendor/laranail/emojis/`, keeping its `css/` and `js/`
directories. File names are not hashed, so the URL survives an update. Re-run the command with `--force`
after `composer update` and the published copy keeps up.

## Theming

Every size is a custom property, read with its token as the fallback, so you can change it at runtime —
on one element, a section, or `:root` — without rebuilding:

```html
<span class="laranail-emoji-box" style="--laranail-emoji-size: 2rem">…</span>
<article style="--laranail-emoji-image-size: 1.3em">…</article>
```

To change the fallbacks themselves, override the tokens in your own build:

```scss
@use 'vendor/laranail/emojis/resources/assets/styles/tokens' with (
  $image-size: 1.25em,
  $image-baseline: -0.25em,
);
@use 'vendor/laranail/emojis/resources/assets/styles/emojis';
```

| Token | Custom property | Default | |
|---|---|---|---|
| `$image-size` | `--laranail-emoji-image-size` | `1.15em` | Image emoji size in text |
| `$image-gap` | `--laranail-emoji-image-gap` | `0.05em` | Horizontal margin around an image emoji |
| `$image-baseline` | `--laranail-emoji-image-baseline` | `-0.2em` | `vertical-align` of an image emoji |
| `$native-baseline` | `--laranail-emoji-native-baseline` | `-0.1em` | `vertical-align` of a native emoji |
| `$box-size` | `--laranail-emoji-size` | `1.5rem` | `.laranail-emoji-box` size |
| `$box-glyph-scale` | `--laranail-emoji-glyph-scale` | `0.86` | Native glyph size inside a box, relative to the box |
| `$font-stack` | — | Apple, Segoe UI, Noto, Twemoji Mozilla, … | Native emoji font order |

The class names are not tokens: the PHP renderer writes them into markup, and a test checks that every class
it writes is defined in the stylesheet.

## Building

The build is only needed when you change the package itself. `public/assets` is committed, so a Composer
install needs no Node.

```bash
npm install
npm run build          # resources/assets → public/assets
npm run watch          # rebuild on change
npm run assets-check   # CI gate: the committed build matches a fresh one
```

`vite.config.mjs` takes every non-partial file in `resources/assets/styles/` (`.scss`, `.css`) to
`public/assets/css/`, every file in `resources/assets/scripts/` (`.js`, `.ts`) to `public/assets/js/`, and
anything they import to `public/assets/<ext>/`.

---

[← Docs index](../../README.md#documentation)
