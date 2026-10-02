# Installation

Install `laranail/emojis` from the laranail VCS repositories and, in Laravel, let package discovery do the rest.

## Requirements

| Requirement | Version | Notes |
|---|---|---|
| PHP | `^8.4.1 \|\| ^8.5` | |
| `ext-mbstring` | any | required |
| `ext-intl` | any | optional; used only for graphemes in the text *between* emoji |
| `ext-dom` | any | optional; needed only to accept SVG emoji images (custom or uploaded), which are sanitised with `DOMDocument` |
| Laravel | `^13.0` | only for the Laravel layer; the core runs without booting it |

The package gives the same results on every PHP build. It does not rely on PCRE's Unicode emoji
properties, on PCRE JIT, or on ICU's grapheme rules — PHP builds still link PCRE2 10.36 and ICU 57, which
get modern emoji wrong or reject the properties outright. See [architecture](architecture.md#why-no-regex-over-the-emoji-set).

## Composer

laranail packages resolve through git, not Packagist. Add the repositories for this package and the
two it depends on to your root `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/emojis" },
    { "type": "vcs", "url": "https://github.com/laranail/console" },
    { "type": "vcs", "url": "https://github.com/laranail/package-tools" }
]
```

Then:

```bash
composer require laranail/emojis:^0.5
```

## Laravel

The service provider and the `Emojis` facade are auto-discovered. Publish the config only if you need to
change a default:

```bash
php artisan vendor:publish --tag=laranail::emojis-config
```

It lands in `config/laranail/emojis.php` and is read as `config('laranail.emojis.*')`. See
[configuration](configuration.md).

The stylesheet is inlined by `<x-laranail-emojis::styles />` and needs no publishing. To serve it as a file
instead, publish the built assets to `public/vendor/laranail/emojis/` and use `<x-laranail-emojis::styles link />`:

```bash
php artisan vendor:publish --tag=laranail::emojis-assets
```

See [styles](tools/styles.md).

Check the install:

```bash
php artisan laranail::package-tools.doctor
```

The `laranail/emojis dataset` line reports the emoji count, the dataset version, and anything running
degraded.

## Plain PHP

No container is needed:

```php
use Simtabi\Laranail\Emojis\Core\Emojis;

$emojis = Emojis::create();                        // defaults
$emojis = Emojis::create(['locale' => ['default' => 'fr']]);   // the same keys as the Laravel config
```

---

[← Docs index](../README.md#documentation)
