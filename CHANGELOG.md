# Changelog

All notable changes to `laranail/emojis` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **`npm-release` finds the publish workflow and the package itself**, so the same file works in every repository
  that publishes to npm: the first of `npm-publish.yml`, `release.yml` and `publish.yml` that takes a `registry`
  input, and in a private workspace root the first workspace's package. It also honours any `vars.X == 'true'`
  gate the workflow declares, not only `NPM_PUBLISH`.

## [0.8.0] - 2026-10-04

### Fixed

- **npm publishing failed with an unexplained 403.** The repository issues GitHub's immutable OIDC subject
  claims (it was created after 2026-07-15), which npm's trusted publishing does not accept for a publish yet
  ([npm/cli#9969](https://github.com/npm/cli/issues/9969)): the token exchange succeeded and the upload was
  refused. `npm-publish.yml` now prints the subject claim and, on failure, names the cause and the fix (an
  `NPM_TOKEN` secret here; enabling direct publish under the trusted publisher's Allowed actions otherwise).
- **Publishing with `NPM_TOKEN` still went through trusted publishing**, and hit the same 403: npm tries the
  OIDC exchange first whenever the job can mint a token. The token branch now publishes without the OIDC
  variables, and without provenance, which needs them, until npm fixes #9969. The token check also reports
  npm's own error and the token's prefix and length (never its value) instead of stopping the job.
- **`npm-release` reported a confirmed GitHub Packages upload as missing**, when the run's log said the version was
  already there: `grep -q` stopped reading at the first match, `gh` died of SIGPIPE, and `pipefail` turned the
  match into a failure. The log is now captured whole before it is searched.
- **`npm-release` takes the registry as an argument** (`npm-release github`, `npm-release npm`, or `auto` by
  default; `--registry=` is the long form, and `--npm`/`--github` still work), and reads the package, the
  repository and the workflow from the checkout it sits in, so the same file serves every repository that
  publishes to npm. In `auto`, a clipboard that holds no `npm_` token now means GitHub Packages without a
  diagnostic of the clipboard's contents.
- **A tag push publishes to the registry the `PUBLISH_REGISTRY` variable names** (`npm` when unset), so a release
  reaches GitHub Packages without a hand-started run while npm will not take one.
- **The README did not say how to install the JavaScript picker.** Its Install section now covers
  `@laranail/emojis-picker` from GitHub Packages: the `.npmrc` scope line, the `read:packages` token it needs,
  and that Blade and Livewire need none of it. `DocumentationTest` holds the npm install line to the release line.
- **Publishing could not proceed while npm refused every token.** `npm-publish.yml` takes a `registry` input
  and publishes to GitHub Packages (`npm.pkg.github.com`) with the run's own `GITHUB_TOKEN`. `.dev/tools/npm-release`
  tries npm first and, when npm will not take a token or refuses a version, publishes that version and every later
  one there instead, in order; `--github` and `--npm` pick one. Installing from GitHub Packages needs an `.npmrc`
  scope line and a token with `read:packages` (see `docs/tools/picker.md`). A re-run for a version GitHub
  Packages already has reports it instead of failing, and `npm-release` confirms each upload from npm's own line
  in the run's log, so it needs no `read:packages` scope on the maintainer's `gh` login.
- **Setting `NPM_TOKEN` failed quietly.** A copied page or a token ID was stored as the secret, and every
  publish then failed with npm's output masked. `.dev/tools/npm-release` (maintainers only, not shipped) checks
  the token's shape and asks npm who it belongs to before setting the secret, confirms GitHub recorded it,
  then publishes every missing version in order and checks each on the registry. It reports npm's status and
  message for a refused token, and only a 401 stops it, since some granular tokens may not ask who-am-I. Invisible characters a copied page carries (zero-width spaces,
  a byte-order mark, non-breaking spaces at the ends) are removed and named, and a refusal names any stray
  character as U+XXXX.
- **Custom emoji never reached Frequently used.** A custom pick was inserted but not recorded, so the section
  held Unicode emoji only. Custom picks, from the grid or from autocomplete, are now remembered beside the
  others (keyed `custom:<name>`, so they cannot collide with a hexcode) and drop out when the payload no
  longer has them or custom emoji are switched off.

## [0.7.0] - 2026-10-04

### Added

- **Keyboard shortcuts.** A shortcut in the target field opens the picker (`Mod+Shift+.` by default, configurable
  as `shortcut` / `picker.shortcut`, `null` to turn it off; matched by physical key so it holds on any
  layout). In the picker, `/` focuses search, Alt+1–9 jumps to a category, and `?` opens the shortcut list.
- **A settings menu** behind a gear in the footer (`picker.features.settings`): the theme (Auto, Light, Dark,
  remembered per user), clearing Frequently used, and the keyboard shortcuts written for the user's
  platform. Same top-layer card and caret as the tone menu.
- **Shortcode autocomplete**, off by default (`picker.features.autocomplete`): typing `:hea` in the field lists
  matching emoji beside the text caret; arrows move, Enter or Tab inserts, Esc dismisses, and the field carries
  the ARIA combobox attributes while it is open.
- **Emoticon search**: `:)` finds 🙂. The payload carries each emoji's emoticons (92 emoji, about 2.5 KB).
- **English keywords in other locales' search**, off by default (`picker.features.english_keywords`, about
  115 KB more payload).
- `openAnchored()`, the tone menu's anchored-popover mechanics, is exported for the settings menu, the
  suggestions and your own menus; also `parseShortcut()`, `matchesShortcut()`, `shortcutLabel()`,
  `bindShortcut()`, `attachAutocomplete()`, `caretRect()` and `openSettingsMenu()`.

- **Full light and dark mode.** The picker follows the OS, then the page's theme on any ancestor — `.dark` /
  `.light`, `[data-theme]`, and now Bootstrap 5.3's `[data-bs-theme]` — then its own `theme`, which JavaScript
  (`theme` option, `picker.theme()`) and React (`theme` prop) can now set too. The palette sets `color-scheme`,
  so the browser's own parts (the search clear button, the set select, scrollbars) match, and the grid's
  scrollbar is thin and themed.

### Changed

- **The picker loads faster.** A closed popover no longer builds its grid on page load, only when it first
  opens. Grids paint about a screenful first and draw the rest in idle time. An embedded payload is parsed
  once per page instead of once per picker. Search folds each emoji's text once. In Chromium with the full
  catalogue, an inline picker's cold first paint went from 156 ms to about 60 ms, and a closed popover's mount
  from 6–12 ms to under a millisecond.

## [0.6.0] - 2026-10-03

### Fixed

- **The picker offered toned emoji the emoji policy refuses.** `PayloadBuilder` checked the policy on each
  emoji but copied its `skins` map unfiltered, so `policy.max_version => '13.0'` still offered 🤝 with tones
  (toned handshakes are Emoji 14.0), and `deny => ['1F44D-1F3FF']` still inserted exactly that sequence. Every
  toned form is now checked on its own. An emoji the policy refuses while allowing some of its toned forms
  (`allow_only => ['1F44D-1F3FD']`) is listed with `"base": false` and offers only those forms; before, it
  vanished. The payload gains `skin_versions`, and the browser's own version cap now drops tones the platform
  cannot draw instead of showing the emoji beside a separate tone square.
- **The Livewire picker failed to mount one time in six**, and took every later picker on the page with it.
  Its target was `#<id>-input`, and a Livewire id starting with a digit makes that an invalid CSS selector,
  which `querySelector` throws on. The target is now `[id='…']`, a selector the browser rejects resolves to
  no target instead of throwing, and one picker failing to mount no longer stops the others.
- **Picks into a React-controlled field were lost.** The text was written through the element's own `value`
  setter, which React shadows to track the value, so React saw no change and wrote its state back over the
  pick. The picker now writes through the prototype's setter, and no longer throws on `type="email"` or
  `type="number"` fields, which have no selection API.
- **The Blade picker vanished inside a Livewire component** after the component's next update: the morph
  stripped the picker's markup but left the element marked as mounted. The mount point now carries
  `wire:ignore`, and a mounted element that lost its picker is mounted again.
- **Custom emoji were always inserted as `:name:`**, ignoring `shortcodes.delimiters`, so with other delimiters
  the text never rendered. The payload carries `delimiters`, and both pickers insert with them.
- **Keyboard, focus and ARIA in both pickers.** ArrowUp and ArrowDown stepped through one flat list, so a
  short last row sent focus to the wrong column or into the wrong section; they now keep the column. Category
  tabs were all tab stops with no `aria-selected`; they are a roving tablist that controls its sections and
  scrolls the picker instead of the page. Tone radios had no arrow keys and lost focus on every change. Escape
  was swallowed even by an inline picker, which blocked a surrounding `<dialog>`; it is now taken only when a
  popover actually closes. Left and Right mirror on right-to-left pages.
- **The popover did not close on a click or Tab outside**, and a pick sent focus to the trigger instead of the
  field. Both are fixed: outside interaction closes it and leaves focus where the user put it, and a pick
  returns focus to the field so typing carries on.
- **Version detection hid too much, or nothing.** With no sample drawn (fonts not yet loaded, no colour emoji
  font) it fell back to Emoji 11.0 and hid everything newer; on macOS, the LastResort font drew a different
  placeholder per block, so missing emoji read as supported. It now waits for web fonts, counts a sample only
  when it comes out in colour as one glyph, caches the result per page, and hides nothing when it cannot tell
  — including on a canvas that adds noise against fingerprinting.
- **Stored state could break the picker.** A corrupt or foreign `recent` value threw on every load until
  storage was cleared, and an out-of-range or string tone left no radio reachable by keyboard. Stored values
  are validated, and tones and columns are clamped everywhere they are read.
- **Lifecycle.** Listeners piled up on every tone change; a slow load from before `destroy()` could draw into a
  remounted picker in the old locale; `Picker.create()` on an auto-initialised element returned a detached copy
  whose `.open()` threw; setters called after `mount()` did nothing. All fixed.
- **Recents.** The vanilla picker never redrew Frequently used after a pick, while the React one redrew it under
  the pointer. Both now show a pick once the popover reopens or the pointer leaves, in the tone it was picked
  in, and drop entries the policy or the version cap no longer offers.
- **Inserting.** Picks now also dispatch `change` (for `wire:model.change`, `x-model.lazy`), respect
  `maxlength`, and work in contenteditable editors.
- **React.** The target's focus listener never re-attached when the ref's element changed; `userKey` changes
  kept the previous user's recents and tone; a server render disagreed with the first client render; a later
  successful load did not clear an earlier error; and two cells could be tab stops. The bundle is marked
  `"use client"`, and its declarations resolve under `moduleResolution: node16`/`nodenext`.
- **The Blade picker dropped its attributes** (`class`, `id`, `style`) and had no `max-version` prop although
  the docs listed one. Unknown values for `tone`, `columns`, `sort` and `recent-order` are corrected.
- **Group names were always English**, whatever the picker's locale, and the interface strings existed only in
  English. Group names and strings now come from `resources/lang/<locale>/picker.php`, shipped for all 24
  dataset locales (machine-seeded; corrections welcome), with "1 result" in the singular.
- **The data block went missing** when the first picker in a request was in a cached fragment or a Livewire
  update. `<x-laranail-emojis::picker-data />` writes it from the layout. In API mode the source URL is relative,
  so it works on any host the app answers on, and pickers share one request per locale.
- **Theming through custom properties did not work as documented.** The picker declared every
  `--laranail-emoji-picker-*` property on itself, so a value set on a wrapper or `:root` was ignored. It now
  only reads them. Dark mode also follows a `.dark` or `[data-theme]` ancestor, and an inline picker no longer
  takes the phone bottom-sheet layout.
- **The shipped OpenAPI file was not valid YAML** (an unquoted comma in a flow mapping), and `/picker` had no
  response schema. Both are fixed, and a test checks the served payload against the schema.
- `ApiSource` dropped `Accept` when given headers, and broke a URL that already had a query string.

### Added

- **Every emoji reachable on every device.** The picker no longer hides what a device cannot draw: with the new
  `render` option at `auto` (the default) it draws those — newer emoji, newer toned forms, flags on Windows —
  as images from the configured set, so all 3,972 records are reachable. `native` keeps the old behaviour,
  `image` draws everything from the set. For Twemoji, Noto, OpenMoji and JoyPixels the payload grows by about
  half a kilobyte (a base URL, a filename rule and the emoji the set lacks); the browser applies the same rule
  as the server, and a shared fixture fails if they ever disagree. Failed images fall back to the glyph.
- **An image set switcher** (`picker.features.set_switcher`): Native, Twemoji, Noto or OpenMoji, remembered
  per user.
- **A skin tone for each person.** A right click, Shift+F10 or a long press on an emoji that takes tones opens
  a menu with its toned forms, or a tone row per person for 🤝 and couples with the result previewed, so mixed
  tones such as 🫱🏻‍🫲🏿 can be picked. The exact form is inserted and remembered.
- A doctor warning when the picker falls back to JoyPixels, which has no coverage data.
- **A `picker` config section.** Every picker option has a default in `laranail.emojis.picker` — placement,
  offset, caret, sheet breakpoint, columns, recents, sort, closing, the trigger, the theme and the payload's
  delivery — and `picker.features` switches parts off (`search`, `recents`, `skin_tones`, `preview`,
  `category_tabs`, `custom`). Component attributes override it, and `data-laranail-emoji-*` attributes override
  both. Values of the wrong type take the built-in default.
- **Kaomoji and Symbols tabs**, off by default (`picker.features.kaomoji`, `.symbols`): an Emoji / Kaomoji /
  Symbols switch over about 2,100 text faces and the special-character groups, inserted as text, with
  `kind` on the select event. The payload carries them only when they are on.
- **A refreshed layout**: outline icons for the category tabs with the current one following the scroll, an
  accent colour (`--laranail-emoji-picker-accent`), sticky uppercase headings, a pill search field, and a
  footer that previews the hovered emoji with its name and `:shortcode:` beside the skin tones.
- **`theme`** (`light`, `dark`, `auto`) and **`delivery`** (`inline`, `api`, `auto`) options, and a
  `laranail/emojis picker` doctor check that warns about a large embedded payload or `api` delivery with the
  API off.
- **A collision-aware popover with a caret.** The picker's popover opens in the top layer, so an ancestor's
  `overflow` or `z-index` can no longer clip it, and is positioned against its trigger: flipped above when
  there is no room below, shifted to stay on screen, capped to the height available, and kept in place on
  scroll and resize. A Bootstrap-5-style caret points at the trigger and turns with the side it opens on
  (`data-placement`). New options in Blade, data attributes, JavaScript and React: `placement`, `offset`,
  `arrow` and `sheetBreakpoint`. `computePosition()`, `autoUpdate()` and `Popover` are exported.
- **A real phone sheet.** Below 640 px (`sheet-breakpoint`) the popover is a bottom sheet with a backdrop, a
  drag handle (down to dismiss, up to expand), the page scroll locked, the safe area kept clear, the sheet
  lifted above the on-screen keyboard, sideways-scrolling tabs, and swipe between categories. Opening it
  focuses the sheet rather than search, so the keyboard does not cover the emoji.
- Search accepts `:shortcode:` and pasted emoji, finds custom emoji, waits for a pause in typing
  (`searchDelay`, 80 ms), and draws at most 200 results.
- A `trigger` option (Blade `trigger`, `data-laranail-emoji-trigger`, React `trigger`) for the trigger's glyph.
- The Livewire component takes `name` and `label`, and its `locale` is locked.
- The picker payload is cached per locale under a key that changes with everything it depends on.
- New theme tokens: `--laranail-emoji-picker-shadow`, `-cell-radius`, `-z` and `-image-size`.
- `./react` exports the pure helpers for a custom UI; the hook gains `detailOf()` and `commitRecents()`.

## [0.5.1] - 2026-10-03

### Fixed

- **CI's sync-check failed with HTTP 403 when GitHub's anonymous rate limit ran out.** Two upstream listings
  come from `api.github.com`, which allows 60 unauthenticated requests an hour per IP, shared by every job on a
  runner's address. `build-dataset.php` now sends `GITHUB_TOKEN` to `api.github.com` (and nowhere else),
  through a private temporary header file rather than the command line, and the static-analysis and weekly
  refresh workflows pass the workflow's token to it.

- **npm publishing moved to its own workflow**, `.github/workflows/npm-publish.yml`, which runs on release tags
  and can be started by hand for a tag that already exists (`gh workflow run npm-publish.yml -f tag=v0.5.0`).
  As a job inside `release.yml` it could only be retried by re-running a tag's run, which uses the workflow as it
  was at the tag. With a token it now checks the token (`npm whoami`) before publishing, so a failure says
  whether the token authenticates at all. `package.json`'s `repository.url` uses the `git+https` form npm
  expects.

## [0.5.0] - 2026-10-02

### Added

- **A React adapter for the emoji picker**: `<EmojiPicker source={…} target={ref} onSelect={…} />` and a
  `useEmojiPicker()` hook, over the same pure state functions as the vanilla picker, rendering the same
  markup, classes and ARIA. React 19 is a peer dependency. See
  [docs/tools/picker.md](docs/tools/picker.md#react).
- **An npm package, `@laranail/emojis-picker`**, versioned with the Composer package: `.` (the vanilla module),
  `./react`, `./styles.css`. The release workflow publishes it with provenance in its own job, which stays off
  until npm publishing is switched on for the repository, so it can never hold up a Composer release.
- The picker's state is exported as pure functions — `buildSections()`, `searchSections()`, `capPayload()`,
  `insertText()`, `indexPayload()`, plus `DEFAULT_STRINGS` and `TONE_SWATCHES` — which the vanilla `Picker`
  now uses, so another renderer can share them.

### Changed

- **The picker module is TypeScript.** `resources/assets/scripts/picker.ts` replaces `picker.js`; the built
  `public/assets/js/picker.js` and its exports are unchanged. `public/assets/js/picker.d.ts` is now generated
  from the source (`npm run types`) instead of written by hand, and `npm run typecheck` fails when the two
  differ.

## [0.4.0] - 2026-10-02

### Added

- **An emoji picker**, modelled on the macOS one: search over names, keywords and shortcodes with accents
  folded, category tabs, frequently used, a remembered skin tone (on every person of 🤝 and 💏), sorting, a
  Custom tab, full keyboard use and ARIA grid semantics, light and dark, right-to-left, and a 44 px bottom
  sheet on phones. Emoji newer than the browser can draw are hidden instead of shown as empty boxes. It is
  `<x-laranail-emojis::picker target="#message" />` with `<x-laranail-emojis::styles picker />` and
  `<x-laranail-emojis::scripts />`; `<livewire:laranail-emojis.picker wire:model="body" />` when Livewire
  is installed; and a dependency-free, CSP-safe ES module (`public/assets/js/picker.js`, with TypeScript
  declarations) with a chainable `Picker`, `ApiSource` and `StaticSource`, auto-initialised from
  `data-laranail-emoji-*` attributes. The emoji come from the HTTP API when it is enabled, otherwise from a
  JSON block embedded once per locale. See [docs/tools/picker.md](docs/tools/picker.md).
- `Emojis::stylesheet()` takes a name: `stylesheet('picker')` is the picker's.

- **A read-only HTTP API**, off by default (`LARANAIL_EMOJIS_API=true`): nine `GET` endpoints under
  `/laranail/emojis/api/v1` for the catalogue (filtered, paged, localized), one emoji by any key, a picker
  payload, symbols, kaomoji, emoticons and status tags. Off means no routes are registered at all. Inputs are
  bounded (422 naming the field, never echoing it), unknown keys are 404s, the configured emoji policy
  applies, responses carry an ETag (304 on a repeat) and `Vary: Accept-Language`, and the default prefix sits
  outside `api/*` so no origin is allowed cross-site until the application says so. Documented in
  [docs/tools/api.md](docs/tools/api.md), with an OpenAPI 3.1 file in `resources/openapi/`.
- `Core\Picker\PayloadBuilder`: everything an emoji picker draws for one locale — the emoji the policy
  permits, grouped, with localized names and keywords and skin-tone maps, then custom emoji. The API serves
  it, and the Blade picker will share it.
- `Emojis::customEmojis()` lists the custom emoji, once each.

- **Status tags: `[OK]`, `[WARN]`, `[FAIL]` and 75 more**, for logs, consoles and plain text, in six groups
  (outcome, severity, task state, test result, change, other), collected from what Symfony, Laravel, Pest and
  the PSR-3 levels already print. Each has a role, a one-character text symbol that is never an emoji, the
  emoji it stands for, and aliases. `Emojis::tags()` (`groups()`, `all()`, `group()`, `get()`, `for()`,
  `search()`), `Emoji::tag()`, and the `Tag` and `TagRole` types. See
  [docs/tools/tags.md](docs/tools/tags.md), generated from `database/sources/curated/tags.json` and checked
  by `composer sync-check`.
- **`Mode::Tag`** writes emoji as tags: `text('✅ Deployed')->to(Mode::Tag)` is `[OK] Deployed`. Emoji that stand
  for no status degrade to their name. `output.tag_template` (default `[{tag}]`) sets the brackets, and
  `output.auto_fallback` accepts `tag`. The convert command takes `--to=tag`.
- `laranail::emojis.export --with=tags,emoticons,kaomoji,symbols` (or `all`) adds those catalogues to the
  export. Without `--with` the document is unchanged.
- `Symbols::all()` lists every symbol.

- `images.custom.urls` (default `true`): set it to `false` to accept only inline images (data URI, base64,
  file) wherever users supply them, since a URL a user chooses is a tracking pixel for every reader.
  `images.custom.max_svg_elements` is now in the published config too; it was read but never listed.
- `Emojis::has()` accepts an `Emoji` or an `EmojiId`, as `find()` does.
- The facade documents every public `Emojis` method (it listed 26 of 47), and a test fails when one is missing or
  its parameters drift. `docs/tools/conversion.md` and `querying.md` now list every public converter and query
  method (`fit()`, `carrier()`, `inCollection()` and `search()` were missing), guarded the same way.
- `README.md` has a Quick start.

### Changed

- **`Mode` has a fourteenth case, `Tag`.** A `match` over `Mode` with no `default` arm needs a `Tag` arm, or it
  throws `UnhandledMatchError` when it meets one.
- **A shortcode remapped with `addShortcode()` now holds for writing as well as reading.** After
  `addShortcode('rocket', 'grinning face')`, `:rocket:` read as 😀 but 🚀 was still written `:rocket:`, so the
  text no longer round-tripped. An emoji is now written with a code that reads back as itself: another of its
  shortcodes, its ASCII code or slug, and if a remap took every one, the character itself (`Ascii` mode
  writes `U+1F680`, staying seven-bit). Adding a code that is not already in use changes nothing.
- **`Emojis::create()` applies a config array's `extend.*` and `input.disabled_emoticons`**, as the Laravel
  provider always has; it used to ignore them without a word. Both now share
  `Core\Extension\ConfiguredExtensions`.
- **The scanner reads the configured `shortcodes.delimiters`.** Output was written with them but only `:code:`
  was read back, so `{rocket}` written under `['{', '}']` stayed text. An empty delimiter is read as `:` rather
  than turning every bare word into a candidate. The doctor's round-trip check uses them too, so it no longer
  fails on a valid configuration.
- `html()` leaves the contents of `title`, `xmp`, `iframe`, `noembed`, `noframes`, `noscript` and `plaintext`
  alone, as it did `script`, `style` and `textarea`: an `<img>` written into a `<title>` shows as markup in the
  tab. The docs now say plainly that `html()` is not a sanitiser.
- The sanitiser also removes U+206A–206F (deprecated format controls), U+1BCA0–1BCA3 (shorthand format
  controls), and the Mongolian free variation selectors unless they follow a Mongolian letter.
- `searchKaomoji($term, 0)` returns every match, as `search()` does with a limit of 0; it returned one.
- `Symbols::search($query, limit: 0)` returns every match too; it returned none, so all three searches now agree.
- Integer config values accept a string of digits, so `env()` works for `input.max_bytes`,
  `policy.max_emojis` and the `images.custom` limits; they used to fall back to the default silently.
- `strict()` throws for a custom emoji that has no form in the target and no fallback, rather than writing its
  shortcode.
- An emoji the dataset gives no emoticon is written with one added through `addEmoticon()`.

### Fixed

- **An unshipped application locale flooded the log.** Laravel's failure reporter forwarded every warning, and
  a search resolves the locale for each emoji it ranks: two searches under an unshipped locale wrote 7,692
  identical warnings. Warnings are now reported once per subject and context, in both the Laravel and the
  PSR-3 reporter, which used to key on the subject alone and so stayed silent about every unshipped locale
  after the first.
- `Emojis::random(Group::Component)` threw a `ValueError`: components are left out of the catalogue by
  default, so the pool was empty. It now draws from the components, and an empty pool throws
  `EmojiNotFound`.
- The image examples registered `:party:` and `:ship:`, which are 🎉 and 🚢, so `addCustom()` threw as written.
  They use free names now, and a docs test fails on any example name that is already a shortcode.
- **The weekly data refresh would have stalled on its first real change.** It regenerated the dataset and the
  enums but not the docs built from them, then ran `composer sync-check`, which checks those docs too. Every
  generator is now listed once in `.dev/tools/lib/Generators.php`; `.dev/tools/regenerate.php` runs them all,
  the refresh calls it, `sync-check` checks the same list, and a test fails when a generator is missing from it.
- Release notes linked docs relatively (`docs/tools/...`), which works in the repository but not on the GitHub
  release page; the release workflow now points them at the tagged files.
- `Emoji::toImage()` labelled the image in the locale configured at boot, not the application's current one.
- The Blade `emoji` component applied `locale` and `set` only in image mode, and an unknown `mode` or `fit`
  surfaced as a bare `ValueError`; it now names the accepted values.
- `SvgSanitizer` inspected only the first DOCTYPE; a second one, or an `ENTITY` outside it, is now refused.
- `Symbols::get('U+02192')` found nothing: a code point with leading zeros was not normalised.
- `truncate()` returned the ellipsis even when it alone was wider than the width asked for.
- `composer.json` said `ext-dom` serves `fromHtml()`, which does not exist (it is for SVG images), and promised
  a `laranail/validation` bridge that was never written. `ext-dom` is in the requirements table now.
- `sanitize --check` said it exits 1 when anything "was removed"; with `--check` nothing is removed.
- Docs: the full list of target-only modes, `->first()` on the Japanese search examples, how SVG is referenced,
  the upgrade notes merged into one section with steps for 0.2 → 0.3 and 0.3 → 0.4, and two 0.2.0 entries
  that named `tools/` after it had moved to `.dev/tools/`.

## [0.3.0] - 2026-10-02

### Added

- **A browsable emoji list**: [docs/tools/emoji-list.md](docs/tools/emoji-list.md) and one page per
  Unicode group, giving each of the 1,932 emoji with its name, shortcode, version and whether it takes skin
  tones. Generated by `.dev/tools/emoji-list-doc.php`; `composer sync-check` fails when it drifts.
- `Symbols::characters(string $group)` returns a group's characters alone (`['←', '↑', '→', …]`), in
  the same order as `group()`, without building a `Symbol` for each.

- **46 more ASCII smileys, 198 in all.** The square, equals and doubled forms (`:]`, `=D`, `=(`, `:))`,
  `:((`), the hyphenated variants (`:-]`, `:'-(`, `:-S`, `:-$`, `:-@`, `>:-D`), and the faces (`^^`,
  `-.-`, `>_<`, `*-*`, `;-;`). Letter- and digit-led ones (`o_O`, `T_T`, `x_x`, `0:)`, `8-)`) match only
  with `withEmoticons(risky: true)`, like the existing `XD` and `8)`. Additive only: every existing
  emoticon keeps its emoji, and every emoji keeps the emoticon `toEmoticons()` writes for it.
- `Emojis::emoticons(bool $risky = false)` lists every emoticon text conversion recognises, including
  ones added with `addEmoticon()`, mapped to its emoji.
- `Emojis::removeEmoticon(string ...$emoticons)`, and `input.disabled_emoticons` in config, switch
  emoticons off everywhere: matching, `emoticons()`, `fromEmoticon()` and `toEmoticons()`.
- [docs/tools/emoticons.md](docs/tools/emoticons.md): the full list, grouped by emoji. It is generated by
  `.dev/tools/emoticons-doc.php`, and `composer sync-check` fails when it drifts from the dataset.

### Changed

- **`symbols.php` shows its characters.** Each record now starts with the character
  (`['→', 'rightwards arrow', …]`), and each group lists its characters, space-separated, instead of code
  points (`'arrows' => '← ↑ → ↓ …'` rather than `'2190 2191 2192 2193 …'`). `Symbols` reads the new shape;
  every group has the same members in the same order, and every record the same fields (checked across all
  7,354). `Symbols` names its record positions instead of repeating bare indexes.

- **The generated data shows its emoji.** `database/generated/` wrote every emoji as an escape, so
  `emojis.php` held `"\u{1F602}"` and never 😂, and kaomoji read `(・\u{2200}・)`. Every visible character
  is now written as itself (`'😂'`, `"👨\u{200D}👩\u{200D}👧"`, `"❤\u{FE0F}"`); only invisible ones (joiners,
  variation selectors, tags, the keycap, controls and odd spaces) stay escaped, so a diff never hides a
  change. The data is identical: all 37 shards decode to the same PHP values as before.
  `tests/Unit/GeneratedDataTest.php` fails if an invisible character is ever written raw.
- The emitter decides what to escape from a fixed list rather than from PCRE's knowledge of each
  character, so a PHP whose PCRE predates Unicode 18 writes the same bytes as one that knows it. 19 Emoji
  18.0 characters (🫫 cracking face among them) had stayed escaped for that reason.

- **`(y)`, `(n)`, `:?` and `<><` are opt-in only.** They read as prose ("Continue? (y) or (n)" became
  "Continue? 👍 or 👎"), so they now match only with `withEmoticons(risky: true)`. To keep one on its own,
  `addEmoticon('(y)', 'thumbs up')`. `toEmoticons()` still writes them.
- An emoticon added with `addEmoticon()` always matches, even where the dataset marks the same text opt-in
  only. `addEmoticon('XD', …)` used to be ignored until the caller also opted in to every risky emoticon.

### Fixed

- **The install command installed 0.1.** `README.md` and `docs/installation.md` said
  `composer require laranail/emojis:^0.1` after `v0.2.0` shipped, and before 1.0 `^0.1` stops at
  `<0.2.0`, so it resolved `v0.1.2`. The `dev-main` branch alias also still said `0.1.x-dev`, which made
  the weekly release-currency check compare `v0.1.2` with `main` and fail. Both now name the current line,
  `docs/release.md` describes the release process as it is actually done (a new tag per release, never a
  moved one), and a test fails when the CHANGELOG, the alias and the install commands disagree.

- `.dev/tools/cross-check.php` checked a hard-coded list of copychar.cc's pages, and skipped a page it
  could not load while still reporting "0 not covered". It now reads the site's sitemap, so a page the site
  adds is checked and reported, and `--strict` fails when a page does not load. The site has twelve pages
  (2026-10-02): the ten character pages, the home page (which repeats "popular") and "about". All 4,758
  characters are covered: an emoji, one of our symbols, or one of 80 excluded by rule.

- `Emoji::emoticons()` missed an emoticon added after that emoji's emoticons were first read, because the
  lookup was memoised and never refreshed.
- `toEmoticons()` wrote an emoji's primary emoticon even after the caller remapped it to another emoji, so
  the output read back as the wrong one. It now writes another emoticon that still means the emoji, or
  degrades.
- The build report now shows how many emoticons each source contributed. The per-source record it keeps
  was collected and never read.

- **The weekly refresh failed on its first run, and would have kept failing at random.** The image-set
  listings come from APIs (GitHub's git-trees endpoint, jsDelivr's listing endpoint) that do not return
  stable bytes: the same request for the same commit-pinned tree came back pretty-printed and minified
  twenty minutes apart, with identical data, so a raw-byte SHA-256 pin failed whenever the format flipped.
  API JSON is now pinned by a hash of its canonical form (`.dev/tools/lib/SourceHash.php`), which still
  changes the moment the data does; plain files are hashed as before. The three listing pins were re-locked;
  the generated dataset is byte-identical.
- Dependabot now also watches `.dev/tools/measure/package.json`, the image-measuring tool the refresh runs.
- The weekly refresh opens its pull request as a GitHub App, with a short-lived token scoped to this repository,
  instead of a personal token or the workflow's own. A pull request opened with the workflow's token starts no
  CI, and checks started any other way are not attached to it, so `main`'s required checks never reported.
  The app's client ID and key are organisation-level, not repository-level.
- Source downloads retry a reset connection too (`curl --retry-all-errors`); the CI sync-check downloads
  about 80 files cold and failed on one reset from jsDelivr.
- The Tests workflow runs on every pull request, Markdown-only ones included, because its checks are now
  required to merge into `main`; a required check that never reports would block a release PR forever.

## [0.2.0] - 2026-09-28

### Added

- **Integration coverage for laranail/console's `ConsoleUIFormatter`.** A test proves the formatter's `icon()`
  and `message()` shortcodes resolve through this catalogue: names only it knows, console's map winning for its
  own, and the ASCII fallback. It skips on console versions before 0.1.4, which have no such methods, so the
  `^0.1.2` floor stays where it is.
- **Your own emoji images, safely.** `EmojiImage::fromDataUri()`, `fromBase64()`, `fromBytes()`, `fromFile()`
  and `fromUrl()` accept PNG, GIF, JPEG, WebP or SVG. Size is checked before decoding, base64 strictly, the
  type is sniffed from the content and must match any declared type, dimensions come from the header (so a
  decompression bomb is refused without decoding it), SVG is sanitised, and embedded images are re-encoded.
  URLs must be `https://` or root-relative and are never fetched. `Emojis::useImage()` replaces an emoji's
  picture; `Emojis::image()` validates without registering; `EmojiImageRule` validates uploads and strings.
  Limits under `images.custom`; `extend.images` in config.
- **SVG sanitiser.** Rebuilds an SVG from an allow-list derived from all 14,631 files of the four pinned sets
  (14,629 render pixel-identical after sanitising; Noto's rainbow flag and package crash resvg before and
  after, so could not be compared): no script, event handler, `foreignObject`, `style`,
  `image`, link, animation, external reference or entity declaration survives. SVG is also only ever rendered
  inside `<img>`.
- **A verified local copy of an image set.** `laranail::emojis.images install {set}` downloads Twemoji, Noto,
  OpenMoji or Fluent into `public/vendor/laranail/emojis/images`, refusing any file whose SHA-256 differs from
  the one shipped for the pinned version, and sanitising the rest; `verify` detects later changes on disk.
  `images.source` = `local` serves installed sets from your own origin. Only hashes ship; no images.
- **Symbols.** `Emojis::symbols()`: 7,354 special characters that are not emoji — arrows, currency, maths,
  numbers, punctuation, letters, symbols, Egyptian hieroglyphs and a popular list — from Unicode's character
  database, with names, blocks and WHATWG HTML entities, searchable. Invisible, control, private-use and
  combining characters are never included.
- **Weekly refresh.** `.dev/tools/refresh.php` moves every source to its newest release, re-locks, re-measures and
  re-hashes, regenerates, and cross-checks; `.github/workflows/refresh.yml` runs it every Monday and opens a
  pull request. `.dev/tools/cross-check.php` proves the catalogue covers every emoji in the Unicode charts and
  everything getemoji.com and copychar.cc offer for copying (their images are not licensed for
  redistribution, and are not used).

### Changed

- **Breaking:** `extend.custom.<name>.url` is renamed `image` (boot names the old key), and a custom emoji's
  image is an `EmojiImage` (`CustomEmoji::$image`, was `$imageUrl`). A refused image throws `InvalidImage`.
- **Breaking:** registering a custom emoji under a name or alias that is already taken throws, instead of
  silently replacing the earlier one.
- **Breaking:** every CSS class is prefixed. Images render as `laranail-emoji laranail-emoji-image` (was
  `emoji`), the Blade component's span as `laranail-emoji laranail-emoji-native` (was `laranail-emoji`), and
  the stylesheet defines nothing unprefixed. `images.class` (default now `''`) adds classes after the
  package's own instead of replacing them.
- The stylesheet source shares one base rule instead of repeating the inline-box reset, and every size is
  themable at runtime through a `--laranail-emoji-*` custom property (image size, gap and baseline, native
  baseline, box size, glyph scale). A test checks that every class the renderer writes is defined in it.
- The maintainer tools moved from `tools/` to `.dev/tools/`. Composer scripts, CI and docs follow; Pint and
  parallel-lint now name the directory explicitly, since their default scan skips dot-directories.
- The doctor check now fails when the configured image set does not resolve, as its description said.
- A strict conversion refused because of a version cap says so.

### Fixed

- `strip()`, `count()`, `length()`, `width()` and `truncate()` were quadratic in the number of emoji: 200 KB
  took 400 s. Linear now.
- Sanitising was quadratic in the number of runs between emoji. Linear now.
- Input over `input.max_bytes` was cut silently and reported clean, so the security rules passed it. It is
  now reported (`Threat::Oversized`) and fails `NoHiddenCharacters` and `EmojiPolicyRule`.
- The HTML converter ended a tag at a `>` inside a quoted attribute, and could write into the attribute;
  `<code/>` and tag-like text inside `<script>`, `<style>` and `<textarea>` confused it.
- HTML-entity output was escaped twice when written into HTML.
- The HTML path ignored the configured carrier.
- The "at most four joiners in a row" limit reset at every emoji.
- Bidi marks (U+200E, U+200F, U+061C) and further invisible characters (soft hyphen, braille blank,
  interlinear annotation, musical format controls, and others) were not removed.
- `AsEmojiText` did not round-trip: a shortcode the user typed came back as an emoji, and an emoji touching
  a letter (`a🚀b`, stored `a:rocket:b`) came back as text. The stored form is now a documented escaped
  format — emoji as `:code:`, a typed `:` or `\` escaped, any other four-byte character as `:U+…:` — so
  every value reads back exactly as written and nothing four-byte is stored. Rows written by 0.1 read as before.
- A default-emoji character followed by VS16 (`⭐️`, as pasted from most sites) was matched without its
  selector, so `strip()` and `toShortcodes()` left an invisible U+FE0F behind.
- A custom-emoji URL with a trailing newline, or starting `/\`, passed the URL check.

### Documentation

- The console-output recipe shows styled output through the formatter, and the terminal page gains a
  *laranail/console* section describing how the two packages connect.

## [0.1.2] - 2026-09-27

### Added

- **Fit — images without the sets' built-in padding.** Every pinned image in Twemoji, Noto, OpenMoji and
  Fluent was measured (`tools/measure`, resvg); `Fit::Balanced` (the new default) removes each set's safe-area
  border while keeping every emoji's relative size, `Fit::Tight` crops to the artwork, `Fit::None` keeps the
  published image. Fitted output is an `<svg>` whose `viewBox` is the crop — no inline styles, strict-CSP
  safe. Per call (`->fit()`), per component (`fit="tight"`) or in config (`images.fit`).
- **Layout CSS for web UI**: `Emojis::stylesheet()` and `<x-laranail-emojis::styles />` (CSP nonce aware) size
  images to the text, pin native colour-emoji glyphs to a tight box (`.laranail-emoji`), and add
  `.laranail-emoji-box` for square containers. The Blade component's native output carries `.laranail-emoji`.
- **Sanitizer**: `Emojis::sanitize()` removes bytes smuggled in variation selectors, instructions hidden in
  tag characters, Trojan Source bidi controls, zero-width fillers, combining floods, orphan emoji components
  and control characters, keeping every real emoji sequence, joiners between letters and ideographic variation
  sequences. Reports counts by kind, never content. `laranail::emojis.sanitize` for the command line.
- **Emoji policy**: `EmojiPolicy` allows or denies emoji by group, subgroup, hexcode, version, unknown and
  custom emoji, with a maximum count and remove-or-replace; config `policy`; rules `NoHiddenCharacters` and
  `EmojiPolicyRule`.
- **Japanese carrier emoji**: `Mode::Carrier` and `Carrier` (docomo, au, SoftBank, Google) read and write the
  carriers' private-use emoji; shared carrier codes resolve through Unicode's `EmojiSources.txt`.
- **Japanese kaomoji**: 1,624 one-line faces from kaomojikan (MIT) with Japanese tags and kana readings, in
  `ja_` groups; `Emojis::searchKaomoji()` searches readings, tags and descriptions. 2,092 faces in all.
- **Collections**: `Emojis::collection('japanese')` and `Query::inCollection()` — the Japanese-text buttons and
  Japanese-origin symbols, places, culture and food.

### Changed

- **Config regrouped by concern** — `locale`, `shortcodes`, `images`, `output`, `input`, `extend`, `policy` —
  so no prefix is repeated (`image_set` → `images.set`, `max_input_bytes` → `input.max_bytes`, extra
  shortcodes, emoticons and custom emoji under `extend`). A config published from 0.1.0 stops boot with a
  message naming each old key and its new place, instead of being read silently as defaults; republish with
  `--force`. `Emojis::create()` takes the same shape.
- Image output defaults to `Fit::Balanced`, so emoji from padded sets render as a cropped `<svg>` rather than
  an `<img>`. Set `images.fit` to `none` for the previous markup.
- **Stylesheet is SCSS, built by Vite.** The source moved from `resources/css/emojis.css` to
  `resources/assets/styles/emojis.scss` (tokens overridable with `@use … with (…)`); the committed build is
  `public/assets/css/emojis.css`, which `Emojis::stylesheet()` reads and which now throws `DatasetException`
  rather than returning an empty string when the file is missing. New publish tag `laranail::emojis-assets`
  copies `public/assets` to `public/vendor/laranail/emojis`, and `<x-laranail-emojis::styles link />` links
  the published file instead of inlining it.
- **Dataset moved to `database/`.** The shipped shards are in `database/generated/` (was `resources/data/`);
  the generator inputs — the upstream lock, the curated JSON and the measured image bounds — are in
  `database/sources/` and are no longer in the Composer archive. The licence notices moved from
  `resources/data/NOTICE.md` to `docs/licences.md`, which the archive still ships. Only code that read the
  shard files by path is affected; `DatasetStore::packaged()` resolves the new location.

### Fixed

- `laranail/console` is required at `^0.1.2`, the first release carrying the `EmojiCatalogue` contract that
  `Laravel\ConsoleEmojiCatalogue` implements. `^0.1` let a lowest-version install resolve console 0.1.0,
  where the adapter's contract does not exist.
- **Every `Emojis` instance loaded its own copy of the dataset.** `DatasetStore::packaged()` built a new
  store per call and the service provider built one per booted application, so wherever opcache is off —
  every CLI process by default — each instance re-parsed about 7.5 MB of shards. The shipped store is now
  one per process (its caches are write-once reads of a fixed directory; a failed load still throws and is
  not cached, so each instance reports its own degradation). This package's suite needed more than 320 MB
  and ran out under the common 256 MB limit; it now fits in 192 MB.

## [0.1.1] - 2026-09-26

### Added

- `Laravel\ConsoleEmojiCatalogue`, implementing laranail/console's `EmojiCatalogue` contract. Console
  discovers it by class name, so installing this package is enough for `Console::emoji()` to resolve
  every shortcode and for console's width measurement to recognise emoji newer than its own table.
  Requires laranail/console 0.1.2 or later, which ships the contract; an older console never loads the class.

## [0.1.0] - 2026-09-26

### Added

- The catalogue: every Unicode Emoji 18.0 emoji — 3,963 fully-qualified plus 9 components — with the
  1,272 minimally- and unqualified forms accepted as input. Typed `Group`, `Subgroup`, `EmojiVersion`,
  `SequenceType`, `SkinTone` enums and a generated `EmojiId` enum with 1,932 cases.
- Lookups by character (any qualification), hexcode, `U+` code point, shortcode in five presets (GitHub,
  emojibase, Slack, JoyPixels, CLDR), slug, emoticon, English CLDR name or `EmojiId`; RGI-only flags
  (`Emojis::flag('KE')`, `flag('GB-SCT')`).
- Skin tones, including one tone per person for handshakes and couples; hair, gender and direction data.
- A fluent `Query` (group, subgroup, type, version range, tone support, text presentation) and ranked,
  localized search over names, keywords and shortcodes.
- Conversion between every form: emoji, text (VS15), unicode, ASCII, emoticon, shortcode, image, HTML
  entity, source-code escape (PHP, JavaScript, Python, CSS), `U+` code point and localized name, with
  explicit degradation chains, a strict mode, version capping (`supportedUpTo()`), skin-tone application,
  post-processing stages, normalisation to fully-qualified sequences, `strip()`, `extract()`, `count()`,
  `isOnlyEmoji()` and cluster-safe `length()`, `width()` and `truncate()`.
- ASCII output is seven-bit for every emoji in the dataset, including Emoji 18 additions that no upstream
  shortcode set covers yet.
- A scanner that does not depend on PCRE Unicode properties, PCRE JIT or ICU, so results are identical on
  every PHP build.
- HTML-safe rendering: plain text is escaped around images; HTML input converts text runs only and leaves
  markup, `code`, `pre`, `script` and attributes byte-identical.
- Image URLs for Twemoji, Noto, OpenMoji, Fluent and JoyPixels, pinned to released versions, with per-set
  coverage so a missing image falls back instead of 404-ing; self-hosting through base URL overrides;
  custom sets through `TemplateImageSet` or the `ImageSet` contract.
- CLDR names and keywords in 24 locales, with composed skin-tone names and locale fallback.
- 470 kaomoji and text faces in 15 groups.
- Custom image emoji (`:laravel:`), extra shortcodes and emoticons, frozen after boot.
- Terminal detection for `Mode::Auto`, with `LARANAIL_EMOJIS` to force it.
- Laravel: facade, namespaced `emoji()` helper, `<x-laranail-emojis::emoji />`, `@laranailEmojis`,
  `AsEmoji` and `AsEmojiText` casts, `NoEmoji`, `ContainsEmoji`, `OnlyEmoji`, `SingleEmoji` and `MaxEmojis`
  rules, the `laranail::emojis.search`, `.show`, `.convert` and `.export` commands, a doctor check and an
  `about` section.

[Unreleased]: https://github.com/laranail/emojis/compare/v0.7.0...HEAD
