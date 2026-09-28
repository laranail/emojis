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
| image-set files, measured (Twemoji, Noto, OpenMoji, Fluent) | pinned releases | not redistributed | margins for Fit, and the SHA-256 that `images install` verifies |
| Unicode `UnicodeData.txt`, `Blocks.txt` | 18.0 | Unicode License V3 | the [symbol](symbols.md) catalogue: names, categories, blocks |
| WHATWG HTML named character references | living standard | CC BY 4.0 | symbols' HTML entities |

Plus curated inputs in `database/sources/curated/`: `emoticons.json` (the everyday emoticons, which win over
every source, and the "risky" list), `aliases.json` (common shortcodes no preset carries), `collections.json`
and `symbols.json` (how the symbol groups are drawn from Unicode).

## Sources we check against but do not copy

| Site | What we use | Why not more |
|---|---|---|
| unicode.org `full-emoji-list`, `full-emoji-modifiers` | the codes, to prove every charted emoji is in the catalogue | the chart images are vendors' artwork, "for illustration only"; reproducing or redistributing them without the owner's permission is prohibited by the Consortium's [Images and Rights](https://www.unicode.org/emoji/images.html) page |
| getemoji.com | the characters it offers for copying, to prove each is recognised | it adds nothing Unicode does not publish |
| copychar.cc | the code points on its ten pages, to prove each is an emoji, one of our symbols, or excluded on purpose | its selection is its own; ours is drawn from Unicode by rule |
| emojipedia.org | nothing | its images "belong to their respective font creators" and its prose is its own |

Apple, Google-in-chart, Microsoft-in-chart, Samsung and Facebook artwork is not available under any licence
that would let a package redistribute it. The four image sets above are, which is why they are the only ones
that can be installed locally.

## Keeping it current

`.dev/tools/refresh.php` moves every source family to its newest release: it asks Unicode's `latest/` directory,
the npm registry and GitHub for the current version, rewrites the lock, downloads and re-hashes the files,
re-measures and re-hashes an image set that moved, regenerates everything, and runs
`.dev/tools/cross-check.php`. A file whose version did not change must still match its locked hash, or the
refresh stops. It talks to a fixed list of hosts over https without redirects.

`.github/workflows/refresh.yml` runs it every Monday and opens a pull request with the report as its body —
never a push to `main`, never an automatic merge. It needs no personal token: the pull request is opened with
the workflow's own token, and because a pull request opened that way starts no other workflows, the workflow
then starts Tests and Static analysis on the refresh branch itself, so the checks `main` requires report on
the pull request as usual. The repository must allow Actions to create pull requests (Settings → Actions →
General → Workflow permissions). Run it by hand with `php .dev/tools/refresh.php` (or `--dry-run` to see
what is behind), and the cross-check alone with `php .dev/tools/cross-check.php --strict`.

The licences travel with the data in [licences](../licences.md), the one docs page the Composer archive
includes.

## Layout

```
database/
├── generated/                 the shipped dataset — written by .dev/tools/build-dataset.php, never edited
│   ├── emojis.php  scanner.php  shortcodes.php  emoticons.php  kaomoji.php
│   ├── images.php  carriers.php  collections.php  symbols.php
│   ├── image-hashes/{set}.php  SHA-256 of each image, for the local installer
│   ├── locales/{locale}.php
│   └── VERSION
└── sources/                   generator inputs — not shipped
    ├── upstream.lock.json     every upstream file: URL, version, licence, sha256
    ├── curated/               aliases.json, collections.json, emoticons.json, symbols.json
    └── measured/              bounds/ (per-image margins) and hashes/ (per-image SHA-256), from .dev/tools/measure
```

## What the generator adds

- **ASCII slugs** for every emoji, transliterated from the Unicode name (`flag_cote_divoire`), so ASCII
  output exists even for Emoji 18 additions no shortcode set covers.
- **Skin-tone links**: each variant points at its base, found by name so mixed-tone handshakes and couples
  (whose tone-less form is not itself an emoji) link correctly.
- **One shortcode index** across presets, higher-priority presets winning; every collision is written to
  `build/dataset-report.txt`.
- **Scanner tables**: every sequence in every qualification, the byte lengths to probe, and character
  classes that replace PCRE's emoji properties. A default-emoji character followed by VS16 (`⭐️`), which
  `emoji-test.txt` does not list but `emoji-variation-sequences.txt` defines, is included, so pasted text
  does not leave a stray selector behind.

## Asserted at build time

The generator fails rather than writing a partial dataset when: a source's sha256 differs from the lock;
the parsed status counts differ from `emoji-test.txt`'s own footer (3,963 / 1,029 / 243 / 9 for 18.0); a
curated entry points at an emoji that does not exist; a keyword contains the storage separator; or an image
listing looks truncated.

---

[← Docs index](../../README.md#documentation)
