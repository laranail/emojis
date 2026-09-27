# Data sources

Upstream sources, pinned by URL and sha256 in `database/sources/upstream.lock.json`, build every shard in `database/generated/`.

## Sources

| Source | Version | Licence | Provides |
|---|---|---|---|
| Unicode `emoji-test.txt`, `emoji-data.txt`, `emoji-variation-sequences.txt` | Emoji 18.0 | Unicode License V3 | the catalogue, CLDR order, groups, qualification, versions, names, `Extended_Pictographic`, text presentation |
| Unicode CLDR annotations and derived annotations | 48.2.0 | Unicode License V3 | names and keywords in 24 locales |
| emojibase-data | 17.0.0 | MIT | shortcode presets (GitHub, emojibase, Slack, JoyPixels, CLDR), emoticons |
| github/gemoji | pinned commit | MIT | GitHub shortcode aliases |
| googlefonts/emoji-metadata | pinned commit | Apache-2.0 | emoticons, 468 kaomoji |
| iamcal/emoji-data | 16.0.0 | MIT | emoticons, Japanese carrier codes |
| Unicode `EmojiSources.txt`, `StandardizedVariants.txt` | 18.0 | Unicode License V3 | canonical carrier mappings; which characters take a variation selector |
| kaomojikan/kaomoji-data | pinned commit | MIT | 1,624 Japanese kaomoji with tags and kana readings |
| image-set file listings (Twemoji, Noto, OpenMoji, Fluent) | pinned releases | listings only | per-set coverage, so no URL points at a missing file |

Plus curated inputs in `database/sources/curated/`: `emoticons.json` (the everyday emoticons, which win over
every source, and the "risky" list) and `aliases.json` (common shortcodes no preset carries).

The licences travel with the data in [licences](../licences.md), the one docs page the Composer archive
includes.

## Layout

```
database/
├── generated/                 the shipped dataset — written by tools/build-dataset.php, never edited
│   ├── emojis.php  scanner.php  shortcodes.php  emoticons.php  kaomoji.php
│   ├── images.php  carriers.php  collections.php
│   ├── locales/{locale}.php
│   └── VERSION
└── sources/                   generator inputs — not shipped
    ├── upstream.lock.json     every upstream file: URL, version, licence, sha256
    ├── curated/               aliases.json, collections.json, emoticons.json
    └── measured/bounds/       per-image margins from tools/measure
```

## What the generator adds

- **ASCII slugs** for every emoji, transliterated from the Unicode name (`flag_cote_divoire`), so ASCII
  output exists even for Emoji 18 additions no shortcode set covers.
- **Skin-tone links**: each variant points at its base, found by name so mixed-tone handshakes and couples
  (whose tone-less form is not itself an emoji) link correctly.
- **One shortcode index** across presets, higher-priority presets winning; every collision is written to
  `build/dataset-report.txt`.
- **Scanner tables**: every sequence in every qualification, the byte lengths to probe, and character
  classes that replace PCRE's emoji properties.

## Asserted at build time

The generator fails rather than writing a partial dataset when: a source's sha256 differs from the lock;
the parsed status counts differ from `emoji-test.txt`'s own footer (3,963 / 1,029 / 243 / 9 for 18.0); a
curated entry points at an emoji that does not exist; a keyword contains the storage separator; or an image
listing looks truncated.

---

[← Docs index](../../README.md#documentation)
