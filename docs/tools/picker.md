# Emoji picker

A dependency-free emoji picker — search, category tabs, recents, skin tones, keyboard and screen-reader
support, a bottom sheet on phones — as a Blade component, a Livewire component, a plain ES module and a React
component.

## Blade

```blade
<head>
    <x-laranail-emojis::styles picker />
</head>
<body>
    <textarea id="message"></textarea>
    <x-laranail-emojis::picker target="#message" />

    <x-laranail-emojis::scripts />
</body>
```

`styles picker` adds the picker's stylesheet to the emoji one, and `scripts` adds the module, which mounts
every picker on the page — including ones added later by Livewire, `wire:navigate` or Turbo. Include both
once per page. Each takes `:nonce` for a strict Content-Security-Policy (Laravel's Vite nonce is picked up on
its own), or `link` to load the published files instead of inlining them
(`php artisan vendor:publish --tag=laranail::emojis-assets`).

| Attribute | Default | Effect |
|---|---|---|
| `target` | — | CSS selector of the input, textarea or contenteditable element to insert into |
| `locale` | `locale.default`, else the application locale | names, keywords, group names, interface strings and search language |
| `inline` | `false` | always open, in the flow, instead of a button and popover |
| `:categories` | all | tabs to show, by slug: `recent`, `smileys_and_emotion`, …, `flags`, `custom` |
| `:max-recent` | `36` | how many recently used emoji to keep |
| `recent-order` | `recent` | `recent` (last used first) or `frequent` (most used first) |
| `sort` | `default` | `default` (Unicode order), `name`, or `newest` |
| `:tone` | `0` | initial skin tone, 0–5, until the user picks one |
| `:columns` | `8` | grid columns; arrow keys move by this |
| `:close-on-select` | `true` | close the popover after a pick |
| `user-key` | — | namespaces recents and tone in storage, for shared devices |
| `max-version` | `auto` | hide emoji newer than this Emoji version; `auto` asks the browser, `''` shows all |
| `trigger` | `🙂` | what the trigger button shows |
| `placement` | `auto` | where the popover opens: `auto` (below, flipping above), `top`, `bottom`, `start`, `end`, each optionally `-start` or `-end` |
| `:offset` | `8` | gap between the trigger and the popover, in px |
| `:arrow` | `true` | draw the caret pointing at the trigger |
| `:sheet-breakpoint` | `640` | at or below this viewport width the popover is a bottom sheet; `0` never |

Any other attribute — `class`, `id`, `style`, `data-*` — is passed through to the mount point. Out-of-range
values are corrected rather than passed on: a tone outside 0–5 is 0, fewer than one column is 8, and an
unknown `sort` or `recent-order` is the default.

Picking inserts at the caret — or at the end of a field nobody has focused yet — then sends focus back to
the field so typing carries on. It dispatches `input` and `change`, so `wire:model`, `wire:model.change`,
`x-model.lazy`, React's controlled inputs and every other framework see it, and it never takes a field past
its `maxlength`. A contenteditable target (Trix, TipTap) gets the text at its selection, through the editor's
own undo history.

Each pick also dispatches a bubbling `laranail-emoji:select` event, after the text is inserted:

```js
document.addEventListener('laranail-emoji:select', (event) => {
  event.detail; // { emoji: '👋🏽', hexcode: '1F44B-1F3FD', name: 'waving hand', shortcode: 'wave', custom: false }
});
```

### Where the emoji come from

With the [HTTP API](api.md) enabled, the picker fetches `/picker`, which is cacheable and keeps the page
small. The URL is relative, so it is fetched from whichever host serves the page, and every picker on the page
shares one request per locale. Otherwise the payload (about 400 KB, less compressed) is embedded once per
locale per request as a `<script type="application/json">` data block, escaped so nothing in it can close the
element.

Embedding once per request assumes the first picker's markup reaches the page. A picker inside a cached
fragment, or one that first appears in a Livewire update, may be the first and still not be on the page. Put
the block in the layout instead, where it always arrives, and every picker finds it:

```blade
<x-laranail-emojis::picker-data />            {{-- the request's locale --}}
<x-laranail-emojis::picker-data locale="fr" />
```

The payload is built once and cached (the application's default cache store), under a key that changes with
everything it depends on — the dataset, the policy, the shortcode settings, the custom emoji and the group
names — so a configuration change never serves a stale one. Either way
`Core\Picker\PayloadBuilder` decides what is offered: the emoji [policy](security.md#emoji-policy-opt-in)
applies, `policy.max_version` caps the version, and custom emoji appear in a Custom tab unless
`allow_custom` is off.

The policy is applied to every skin-tone form as well as to each emoji, because a toned form can be newer
than its emoji (🤝 is Emoji 3.0, its toned forms 14.0) and can be denied or allowed on its own:

- A toned form the policy refuses is left out of the emoji's `skins` map, and picking that tone inserts the
  untoned emoji instead.
- An emoji the policy refuses while allowing some of its toned forms (`allow_only => ['1F44D-1F3FD']`) is
  still listed, marked `"base": false`, and the picker inserts only those forms.
- `skin_versions` carries the version of the toned forms newer than their emoji — one string when they share
  it, otherwise tone key → version — so the browser's own version cap applies to tones too.

Custom emoji are inserted between the configured `shortcodes.delimiters`, which the payload carries as
`delimiters`, so the text reads back through the scanner.

The picker also hides emoji newer than the browser can draw. Once web fonts have loaded, it draws one sample
per Emoji version on a canvas and keeps everything up to the newest one that comes out in colour and as a
single glyph — a placeholder box, or a sequence drawn as its parts, does not count. When it cannot tell (no
canvas, no colour emoji font, or a canvas that adds noise against fingerprinting), it hides nothing. Set
`data-laranail-emoji-max-version` (or `maxVersion()` in JavaScript) to a version to cap it yourself, or to
`''`/`null` to show everything.

## The popover

The popover opens in the browser's top layer (the `popover` attribute), so no `overflow: hidden` or
`z-index` on an ancestor can clip or cover it. It is positioned against its trigger the way Popper and
Floating UI position theirs: offset from the trigger, flipped to the other side when the preferred one is too
small, shifted along the edge to stay on screen, capped to the height left, and kept there as the page
scrolls or resizes. A trigger scrolled out of view takes the popover with it.

Its caret is drawn the way Bootstrap 5 draws a popover arrow: two CSS triangles, the outline in the border
colour and the fill in the background colour. The side the popover landed on is in `data-placement`
(`bottom-start`, `top-start`, `right-center`, …), so a flip turns the caret with no script. The script only
moves the caret along the edge so it points at the trigger's centre, and keeps it clear of the rounded
corners. Size it with `--laranail-emoji-picker-arrow-width` and `-arrow-height`, or turn it off with
`:arrow="false"`. The popover grows out of the caret as it opens, unless reduced motion is set.

### On phones

At or below `sheet-breakpoint` (640 px by default), the popover is a bottom sheet instead:

- a backdrop that closes it when tapped, and the page behind it does not scroll;
- a drag handle: drag down to dismiss, up to expand to nearly full height (focusing search expands it too);
- 44 px targets, category tabs that scroll sideways, and a horizontal swipe across the emoji to move
  between categories;
- the safe area at the bottom kept clear, and the sheet lifted above the on-screen keyboard through the
  visual viewport.

Opening the sheet focuses the sheet, not the search field, so the keyboard does not cover half the emoji
until the user asks for it.

The positioning is exported as plain functions for other uses — `computePosition()` (pure, testable without
a browser), `autoUpdate()` and the `Popover` controller both adapters use.

## Searching

The search matches names, shortcodes and keywords in the picker's locale, every word required, best match
first. `:smile`, `:smile:` and `smile` are the same search; a pasted emoji finds itself, toned or not; custom
emoji match by name and label. It waits for a pause in typing (80 ms, `searchDelay`) and draws at most 200
results.

## Livewire

```blade
<livewire:laranail-emojis.picker wire:model="body" />
<livewire:laranail-emojis.picker wire:model="body" locale="fr" placeholder="Say something" :rows="4" name="body" label="Message" />
```

A textarea with the picker beside it, bound to the parent's property. `name` names the textarea for a form that
also posts without Livewire, and `label` is its accessible name (the placeholder otherwise). `locale` is fixed
once mounted. Registered only when
`livewire/livewire` is installed. The Blade picker pointed at any `wire:model` field does the same without it:
its mount point carries `wire:ignore`, so a component update does not strip the mounted picker. Point it at
the field with an attribute selector (`[id='…']`) when the id is generated — `#id` cannot express an id that
starts with a digit, and Livewire's do one time in six.

## JavaScript

```js
import { Picker, ApiSource, StaticSource, localStorageStore } from '/vendor/laranail/emojis/js/picker.js';

const picker = await Picker.create(document.querySelector('#picker'))
  .source(new ApiSource('/laranail/emojis/api/v1'))   // or new StaticSource(payload | 'element-id')
  .target('#message')
  .locale('fr')
  .skinTone(3)
  .history({ max: 24, order: 'frequent', store: localStorageStore('app') })
  .sort('newest')
  .categories(['recent', 'smileys_and_emotion', 'people_and_body'])
  .on('select', ({ emoji }) => console.log(emoji))
  .mount();

picker.destroy();
```

Setters called after `mount()` take effect: display options redraw, and `source()`, `locale()` and
`maxVersion()` reload. `Picker.create()` on an element that already has a live picker — one the module
auto-initialised, say — returns that picker, so `.on()` and `.open()` reach the one on screen. A slow load
that a remount or a locale change overtakes is discarded, and `destroy()` aborts it.

`ApiSource` takes `fetch` options as its second argument; headers passed there are merged with
`Accept: application/json`, and a URL with its own query string keeps it:

```js
new ApiSource('/laranail/emojis/api/v1?tenant=7', { headers: { 'X-CSRF-TOKEN': token } });
```

Every option also reads from `data-laranail-emoji-*` attributes on a `[data-laranail-emoji-picker]`
element. Importing the module mounts those automatically; set `globalThis.__laranailEmojiNoAutoInit = true`
first to mount by hand. The module is written in TypeScript (`resources/assets/scripts/picker.ts`), and its
declarations ship beside the build as `js/picker.d.ts`, generated from the source by `npm run types` — a
check fails when they differ.

The state behind the picker is exported as pure functions — `buildSections()`, `searchResults()`,
`capPayload()`, `insertText()`, `recordRecent()`, `readRecent()`, `withTone()`, `gridTarget()`,
`rovingIndex()` and the rest — so another renderer can reuse it.

## React

```tsx
import { useRef } from 'react';
import { EmojiPicker, ApiSource } from '@laranail/emojis-picker/react';
import '@laranail/emojis-picker/styles.css';

const source = new ApiSource('/laranail/emojis/api/v1'); // once, outside render

export function Composer() {
  const message = useRef<HTMLTextAreaElement>(null);

  return (
    <>
      <textarea ref={message} />
      <EmojiPicker source={source} target={message} locale="fr" onSelect={({ emoji }) => console.log(emoji)} />
    </>
  );
}
```

`<EmojiPicker>` takes the same options as the vanilla picker as props — `source`, `target` (a ref),
`locale`, `tone`, `maxRecent`, `recentOrder`, `sort`, `categories`, `columns`, `maxVersion`, `inline`,
`closeOnSelect`, `userKey`, `store`, `strings`, `searchDelay`, `trigger`, `placement`, `offset`, `arrow`,
`sheetBreakpoint` — and `onSelect`. It renders the
same markup, classes and ARIA, so `picker.css` styles it and the keyboard behaviour is the same. The target
may be a controlled `<textarea value={…} onChange={…}>`; the pick lands in its state. To draw your own UI, use
the hook behind it:

```tsx
const picker = useEmojiPicker({ source, locale: 'fr', onSelect });
// picker.sections, picker.tabs, picker.query / setQuery, picker.tone / setTone, picker.status
// picker.detailOf(item) — what a pick would insert; picker.select(item) — record it and call onSelect
// picker.commitRecents() — show new picks in Frequently used once nothing is under the pointer
```

`./react` also exports the pure building blocks (`charOf`, `withTone`, `insertText`, `gridTarget`,
`rovingIndex`, `searchResults`, …) without the vanilla module's auto-init. The bundle is marked
`"use client"`, and it reads stored tone and recents in an effect, so a server render and the first client
render agree; changing `userKey` or `store` reads them again. Both adapters are thin over the same pure
functions, so the two cannot behave differently. React 19 is a peer dependency; nothing else is.

The package is `@laranail/emojis-picker` on npm, with the version of the Composer package: `.` is the vanilla
module, `./react` the React adapter, `./styles.css` the picker stylesheet. `.github/workflows/npm-publish.yml` publishes it
with provenance on every release tag once npm publishing is switched on for the repository (the
`NPM_PUBLISH` variable), and a maintainer can publish an existing tag by hand with
`gh workflow run npm-publish.yml -f tag=vX.Y.Z`. To build it from a checkout instead:
`npm run build:react && npm run types && npm pack`.

## Accessibility

- The trigger is a button with `aria-haspopup="dialog"` and `aria-expanded`; Escape closes the popover and
  returns focus to it. A click or Tab outside closes it and leaves focus where the user put it. An inline
  picker is a group, not a dialog, and leaves Escape to the page.
- The category bar is an ARIA tablist: one tab in the tab order, `aria-selected` on the current one, arrow
  keys, Home and End to move, and each tab controls its section. A tab scrolls the picker, never the page.
- Each section is an ARIA grid labelled by its heading, and each cell is named with its localized CLDR
  name. One cell is in the tab order; arrow keys move by cell and by row, keeping the column across a short
  last row and into the next section; PageUp and PageDown jump a section; Home and End go to the ends; Enter
  and Space pick; ArrowDown from the search field enters the grid and ArrowUp from the first row returns.
  Left and Right swap on right-to-left pages.
- The tone control is a radio group that the arrow keys move through, and a live region announces the
  number of results ("1 result", "12 results").
- Frequently used does not redraw under the pointer: a pick shows there once the popover reopens or the
  pointer leaves.
- `forced-colors` and `prefers-reduced-motion` are respected, and logical properties mirror the layout on
  right-to-left pages.
- On phones the popover becomes a bottom sheet with 44 px targets; an inline picker stays in the flow.

## Security and privacy

The module builds the DOM with `createElement` and `textContent` only — never `innerHTML` or `eval` — so a
name or label cannot inject markup, and it runs under a strict Content-Security-Policy with a nonce.
Storage holds hexcodes, counts and times only, never text the user typed, under a namespaced key; private
mode, a full quota or blocked storage fall back to memory without an error.

## Styling

Every class is `.laranail-emoji-picker*`, and colours and sizes are custom properties. The picker reads
them without declaring them, so they can be set on the picker, on its mount point, or anywhere above it:

```css
:root {
  --laranail-emoji-picker-bg: #0d1117;
  --laranail-emoji-picker-fg: #e6edf3;
  --laranail-emoji-picker-cell: 2.5rem;
  --laranail-emoji-picker-height: 26rem;
}
```

```blade
<x-laranail-emojis::picker target="#message" class="composer-picker" style="--laranail-emoji-picker-radius: 4px" />
```

| Property | Default (light / dark) |
|---|---|
| `--laranail-emoji-picker-bg` | `#fff` / `#1f2328` |
| `--laranail-emoji-picker-fg` | `#1f2328` / `#f0f3f6` |
| `--laranail-emoji-picker-muted` | `#59636e` / `#9198a1` |
| `--laranail-emoji-picker-border` | `#d1d9e0` / `#3d444d` |
| `--laranail-emoji-picker-hover` | `#eef1f4` / `#2a313c` |
| `--laranail-emoji-picker-focus` | `#0969da` / `#4493f8` |
| `--laranail-emoji-picker-shadow` | a soft drop shadow |
| `--laranail-emoji-picker-radius` | `12px` |
| `--laranail-emoji-picker-cell-radius` | `8px` |
| `--laranail-emoji-picker-cell` | `2.25rem` (`2.75rem` on phones) |
| `--laranail-emoji-picker-width` | columns × cell + padding |
| `--laranail-emoji-picker-height` | `22rem` |
| `--laranail-emoji-picker-z` | `50` |
| `--laranail-emoji-picker-image-size` | `1.5rem` (custom emoji images) |
| `--laranail-emoji-picker-arrow-width` | `1rem` |
| `--laranail-emoji-picker-arrow-height` | `0.5rem` |
| `--laranail-emoji-picker-backdrop` | `rgb(0 0 0 / 0.4)` (phone sheet) |

Dark follows `prefers-color-scheme`, unless the page says otherwise: a `.dark` or `[data-theme="dark"]`
ancestor turns it on (Tailwind's class strategy and most theme switchers), and `[data-theme="light"]` or
`.light` keeps it off.

## Translations

The interface strings and group names are in `resources/lang/<locale>/picker.php`, one file for each of the
dataset's 24 locales, named by its tag (`zh-Hant`, `pt`). English is the source; the others were seeded by
machine translation, and corrections are welcome. Publish the translations
(`--tag=laranail::emojis-translations`) to change them. A string a locale lacks falls back to English.

---

[← Docs index](../../README.md#documentation)
