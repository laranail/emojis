# laranail/emojis

[![Version](https://img.shields.io/github/v/tag/laranail/emojis?label=version)](https://github.com/laranail/emojis/tags)
[![Tests](https://github.com/laranail/emojis/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/emojis/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/emojis/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/emojis/actions/workflows/static-analysis.yml)
[![License MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

The version badge reads the GitHub tag: the family resolves through VCS repositories, not Packagist, so there
is no registry listing to show.

> Every Unicode emoji, and 7,000 special characters, as a fluent, typed PHP catalogue — render and convert
> between emoji, text, ASCII, emoticon, shortcode, image and escaped forms, for web and CLI, with a Laravel layer.

PHP `^8.4.1 || ^8.5`. The core runs without booting Laravel; the Laravel layer targets Laravel `^13.0`.

## Install

Add the laranail VCS repositories to your root `composer.json` (see
[installation](docs/installation.md)), then:

```bash
composer require laranail/emojis:^0.1
```

## <a name="documentation"></a>Documentation

Hosted at <https://opensource.simtabi.com/documentation/laranail/emojis/>.

### Guides

- [Installation](docs/installation.md) — requirements, VCS repositories, extensions
- [Getting started](docs/getting-started.md) — the five calls you will use most
- [Configuration](docs/configuration.md) — every key in `config/laranail/emojis.php`
- [Modes and conversion](docs/modes.md) — the conversion matrix and how degradation works
- [Architecture](docs/architecture.md) — the scanner, the dataset, failure handling, and why
- [Release](docs/release.md) — versioning, the moving tag, and refreshing the dataset

### Reference

- [Catalogue and lookups](docs/tools/catalogue.md) — `Emojis`, `Emoji`, `EmojiId`, flags, skin tones
- [Querying and search](docs/tools/querying.md) — `Query`, ranked localized search
- [Text conversion](docs/tools/conversion.md) — `TextConverter`, every option and inspection method
- [Emoticons](docs/tools/emoticons.md) — every ASCII smiley recognised, and the emoji it converts to
- [Emoji list](docs/tools/emoji-list.md) — every emoji by group, with its name, shortcode and version
- [Images](docs/tools/images.md) — image sets, fit (padding removal), a verified local copy, your own images, SVG sanitising
- [Symbols](docs/tools/symbols.md) — 7,354 special characters: arrows, currency, maths, letters, hieroglyphs
- [Styles](docs/tools/styles.md) — the stylesheet: inline, link or `@use` the SCSS; tokens; the Vite build
- [Terminal output](docs/tools/terminal.md) — `Mode::Auto`, width and truncation
- [Localisation](docs/tools/localisation.md) — shipped locales and fallback
- [Kaomoji](docs/tools/kaomoji.md) — text faces by group
- [Security and safety](docs/tools/security.md) — sanitising hidden payloads, emoji policies
- [Japanese emoji](docs/tools/japanese.md) — carrier emoji, the Japanese collection, names
- [Extending](docs/tools/extending.md) — custom emoji, shortcodes, emoticons, image sets, macros
- [Laravel integration](docs/tools/laravel.md) — facade, helper, Blade, casts, rules, commands
- [Data sources](docs/tools/data-sources.md) — where every field comes from, what we check against, the weekly refresh
- [Licences](docs/licences.md) — the third-party data notices

### Recipes

- [Sanitise user input](docs/recipes/sanitise-user-input.md)
- [Render emoji as images in Blade](docs/recipes/render-emoji-as-images.md)
- [Emoji in console output](docs/recipes/emoji-in-console-output.md)
- [Store emoji in a utf8mb3 column](docs/recipes/store-emoji-in-utf8mb3.md)
- [Feed a JavaScript emoji picker](docs/recipes/feed-a-javascript-picker.md)
- [Target older platforms](docs/recipes/target-older-platforms.md)
- [Fit emoji into UI without padding](docs/recipes/fit-emoji-into-ui.md)
- [Block hidden prompt injection in user text](docs/recipes/block-hidden-prompt-injection.md)
- [Use your own emoji images](docs/recipes/use-your-own-emoji-images.md)
- [Serve emoji images from your own origin](docs/recipes/serve-emoji-images-locally.md)
- [Build a special-character picker](docs/recipes/build-a-special-character-picker.md)

## Contributing & security

See [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately to `security@simtabi.com` — see
[SECURITY.md](SECURITY.md).

## License

MIT — see [LICENSE](LICENSE). The generated data carries the Unicode, MIT and Apache-2.0 notices in
[docs/licences.md](docs/licences.md).
