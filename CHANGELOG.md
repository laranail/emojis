# Changelog

All notable changes to `laranail/emojis` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
