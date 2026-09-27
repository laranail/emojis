# Changelog

All notable changes to `laranail/emojis` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
