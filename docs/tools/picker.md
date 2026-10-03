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
| `target` | — | CSS selector of the input or textarea to insert into |
| `locale` | application locale | names, keywords and search language |
| `inline` | `false` | always open, in the flow, instead of a button and popover |
| `:categories` | all | tabs to show, by slug: `recent`, `smileys_and_emotion`, …, `flags`, `custom` |
| `:max-recent` | `36` | how many recently used emoji to keep |
| `recent-order` | `recent` | `recent` (last used first) or `frequent` (most used first) |
| `sort` | `default` | `default` (Unicode order), `name`, or `newest` |
| `:tone` | `0` | initial skin tone, 0–5, until the user picks one |
| `:columns` | `8` | grid columns; arrow keys move by this |
| `:close-on-select` | `true` | close the popover after a pick |
| `user-key` | — | namespaces recents and tone in storage, for shared devices |

Picking inserts at the caret — or at the end of a field nobody has focused yet — and dispatches `input`, so
`wire:model`, Alpine and every framework see the change. It also dispatches a bubbling
`laranail-emoji:select` event:

```js
document.addEventListener('laranail-emoji:select', (event) => {
  event.detail; // { emoji: '👋🏽', hexcode: '1F44B-1F3FD', name: 'waving hand', shortcode: 'wave', custom: false }
});
```

### Where the emoji come from

With the [HTTP API](api.md) enabled, the picker fetches `/picker`, which is cacheable and keeps the page
small. Otherwise the payload (about 400 KB, less compressed) is embedded once per locale per request as a
`<script type="application/json">` data block, escaped so nothing in it can close the element. Either way
`Core\Picker\PayloadBuilder` decides what is offered: the emoji [policy](security.md#emoji-policy-opt-in)
applies, `policy.max_version` caps the version, and custom emoji appear in a Custom tab unless
`allow_custom` is off.

The picker also hides emoji newer than the browser can draw: it renders one sample per Emoji version on a
canvas and drops anything newer than the newest that is not an empty box. Set
`data-laranail-emoji-max-version` (or `maxVersion()` in JavaScript) to a version to cap it yourself, or to
`''`/`null` to show everything.

## Livewire

```blade
<livewire:laranail-emojis.picker wire:model="body" />
<livewire:laranail-emojis.picker wire:model="body" locale="fr" placeholder="Say something" :rows="4" />
```

A textarea with the picker beside it, bound to the parent's property. Registered only when
`livewire/livewire` is installed. The Blade picker pointed at any `wire:model` field does the same without it.

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

Every option also reads from `data-laranail-emoji-*` attributes on a `[data-laranail-emoji-picker]`
element. Importing the module mounts those automatically; set `globalThis.__laranailEmojiNoAutoInit = true`
first to mount by hand. The module is written in TypeScript (`resources/assets/scripts/picker.ts`), and its
declarations ship beside the build as `js/picker.d.ts`, generated from the source by `npm run types` — a
check fails when they differ.

The state behind the picker is exported as pure functions — `buildSections()`, `searchSections()`,
`capPayload()`, `insertText()`, `recordRecent()`, `withTone()` and the rest — so another renderer can reuse
it.

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
`closeOnSelect`, `userKey`, `store`, `strings` — and `onSelect`. It renders the same markup, classes and
ARIA, so `picker.css` styles it and the keyboard behaviour is the same. To draw your own UI, use the hook
behind it:

```tsx
const picker = useEmojiPicker({ source, locale: 'fr', onSelect });
// picker.sections, picker.query / setQuery, picker.tone / setTone, picker.select(item), picker.status
```

Both are thin over the same pure functions the vanilla picker uses, so the two cannot behave differently.
React 19 is a peer dependency; nothing else is.

The package is `@laranail/emojis-picker` on npm, with the version of the Composer package: `.` is the vanilla
module, `./react` the React adapter, `./styles.css` the picker stylesheet. `.github/workflows/npm-publish.yml` publishes it
with provenance on every release tag once npm publishing is switched on for the repository (the
`NPM_PUBLISH` variable), and a maintainer can publish an existing tag by hand with
`gh workflow run npm-publish.yml -f tag=vX.Y.Z`. To build it from a checkout instead:
`npm run build:react && npm run types && npm pack`.

## Accessibility

- The trigger is a button with `aria-haspopup="dialog"` and `aria-expanded`; Escape closes the popover and
  returns focus to it.
- Each section is an ARIA grid labelled by its heading, and each cell is named with its localized CLDR
  name. One cell is in the tab order; arrow keys move by cell and by row, Home and End jump, Enter and
  Space pick, and ArrowDown from the search field enters the grid.
- The tone control is a radio group, and a live region announces the number of results.
- Light and dark follow `prefers-color-scheme`; `forced-colors` and `prefers-reduced-motion` are
  respected, and logical properties mirror the layout on right-to-left pages.
- Below 480 px the popover becomes a bottom sheet with 44 px targets.

## Security and privacy

The module builds the DOM with `createElement` and `textContent` only — never `innerHTML` or `eval` — so a
name or label cannot inject markup, and it runs under a strict Content-Security-Policy with a nonce.
Storage holds hexcodes, counts and times only, never text the user typed, under a namespaced key; private
mode, a full quota or blocked storage fall back to memory without an error.

## Styling

Every class is `.laranail-emoji-picker*`, and colours and sizes are custom properties:

```css
.laranail-emoji-picker {
  --laranail-emoji-picker-bg: #0d1117;
  --laranail-emoji-picker-fg: #e6edf3;
  --laranail-emoji-picker-cell: 2.5rem;
  --laranail-emoji-picker-height: 26rem;
}
```

The interface strings are in `resources/lang/en/picker.php`; publish the translations to change them or add
a language.

---

[← Docs index](../../README.md#documentation)
