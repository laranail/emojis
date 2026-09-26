# Localisation

CLDR names and keywords ship for 24 locales; everything else falls back to English.

## Shipped locales

`en`, `ar`, `bn`, `de`, `es`, `fa`, `fr`, `hi`, `id`, `it`, `ja`, `ko`, `nl`, `pl`, `pt`, `ru`, `sv`, `sw`,
`th`, `tr`, `uk`, `vi`, `zh`, `zh-Hant` — `Emojis::availableLocales()` lists them. They come from CLDR's
annotations plus its derived annotations (flags, gendered and hair sequences). At least 93% of emoji have a
localized name in every shipped locale; the rest — Emoji 18 additions newer than the pinned CLDR release —
use the English name.

## Using a locale

```php
Emojis::get('rocket')->name('fr');                    // "fusée"
Emojis::get('rocket')->keywords('de');                // ["Rakete", "Weltraum", …]
Emojis::text('🚀 👋🏽')->locale('fr')->toNames();      // "[fusée] [signe de la main: peau légèrement mate]"
Emojis::search('fusée', 'fr');
```

In Laravel, with `locale` left `null` in config, every call follows `app()->getLocale()` at the moment of
the call.

## Resolution

A requested tag resolves through: the exact tag (`pt-BR`), the script (`zh-TW` and `zh-HK` → `zh-Hant`),
the language (`pt`), `fallback_locale`, then English. Underscores are accepted (`pt_BR`).

An unshipped locale is expected and is logged once as a warning. A shipped locale file that fails to load
is a degradable failure: names fall back to English, the failure is reported once, and it shows in
`Emojis::reporter()->degradations()` and the doctor.

## Skin-tone names

Skin-tone variants are not stored per locale. Their names are composed from the base emoji's name and the
tone names ("signe de la main: peau légèrement mate"), which is how CLDR derives them and keeps each
locale file to a few hundred kilobytes.

---

[← Docs index](../../README.md#documentation)
