# Changelog

All notable changes to `laranail/emojis` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `images.custom.relative` (`ImagePolicy::$allowRelative`, `allowsRelative()`): whether root-relative image
  URLs are accepted. Unset, they are accepted only while `images.custom.hosts` is empty — see Security.
- `InvalidPolicy`, thrown when `policy.allow_unknown` or `policy.allow_custom` is not a boolean.

### Changed

- Release tags are final: each release gets its own number, with release candidates first, and
  `docs/release.md` describes the process. The moving-`v0.1.0` history is kept there for consumers who
  installed during it.
- Test tooling: Pest 5 (with its arch and Laravel plugins) and PHPUnit 13. Development only; nothing an
  application installs changes.
- The picker embeds its payload, instead of fetching it, while `api.keys` is set: a browser page cannot
  send the key. The doctor check says so.
- Warnings are logged once per distinct value and at most 32 per subject per worker.

### Fixed

- Under Octane, names now follow the request's `App::setLocale()`; the boot locale was used before.
- An unknown `?locale=` value is no longer kept in memory for the worker's life.
- The picker payload cache is keyed on the active emoticons and the package version, so disabling an
  emoticon or upgrading no longer serves a stale payload for up to a day.
- The picker payload cache hits again when `cache.serializable_classes` is `false`.
- The picker script no longer shares one API request between sources sending different credentials.
- `AsEmojiText` reads back an emoji written without its U+FE0F exactly as written.
- `laranail::emojis.images verify` reports a tampered manifest path as changed instead of crashing.
- `build-dataset` times out stalled downloads and re-fetches a corrupt cached source with `--fetch`.
- Rector 2.9 removed the early-return set this package's config named; the static analysis job ran no
  rule until it was dropped.
- `release.yml` failed on a pre-release tag (`v0.1.1-rc.1` looked for a `[0.1.1-rc.1]` section); a
  pre-release now takes its notes from its final version's section and is published as a pre-release.

### Security

- A root-relative custom image URL (`/logout`, an admin GET) skipped `images.custom.hosts`, so every
  reader's browser sent that request to the application with their cookies. With `hosts` set, root-relative
  URLs are now refused unless `images.custom.relative` is `true`.
- `policy.allow_unknown` and `policy.allow_custom` read `0`, `'0'` and `'false'` as allow, so a configured
  deny did nothing. They now read booleans the way `env()` writes them, and refuse anything else.
- `EmojiPolicyRule` rejects invalid UTF-8, as the other emoji rules do.
- The release workflow grants write access to its job only, and its checkout no longer keeps the token.

## [0.1.0] - 2026-11-15

The first release. The package carries one tag, `v0.1.0`, and moves it on each change; consumers require
`^0.1`.

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
- An emoji picker for Blade, Livewire, React or a plain ES module: search, group tabs, recents, skin tones,
  kaomoji and symbol tabs, an image-set switcher, full keyboard support and a mobile layout. The built
  picker ships inside the Composer package; `@laranail/emojis-picker` publishes the same code for a
  JavaScript build.
- 7,354 special characters (arrows, currency, maths, letters, hieroglyphs) and 78 status tags such as
  `[OK]` and `[WARN]`, with `Mode::Tag`.
- An HTTP API, off by default: nine read-only JSON endpoints for emoji, the picker payload, symbols, kaomoji
  and tags, with optional bearer API keys (`api.keys`), cross-origin reads for listed origins
  (`api.allowed_origins`) and hardening headers on every response. An OpenAPI contract describes it.
- A weekly refresh of the upstream emoji data, and a showcase page with every picker feature live
  (`composer demo`).

[Unreleased]: https://github.com/laranail/emojis/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/laranail/emojis/releases/tag/v0.1.0
