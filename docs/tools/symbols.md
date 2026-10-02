# Symbols

7,354 special characters that are not emoji — arrows, currency signs, maths, numbers, punctuation, letters
with diacritics, typographic symbols and Egyptian hieroglyphs — through `Emojis::symbols()`
(`Simtabi\Laranail\Emojis\Core\Symbols\Symbols`).

## Groups

| Group | Count | Drawn from |
|---|---:|---|
| `popular` | 103 | a hand-picked list: ©, ®, ™, °, ±, ×, →, ✓, €, … |
| `arrows` | 616 | the Arrows blocks, plus arrows in Dingbats, Miscellaneous Technical and Symbols and Arrows |
| `currency` | 75 | every currency sign (`Sc`), plus 元 円 圆 圓 圜 원 ㍐ |
| `math` | 755 | the mathematical operator and symbol blocks, superscripts and subscripts |
| `numbers` | 1,019 | fractions, superscripts, circled and Roman numerals, mathematical and fullwidth digits |
| `punctuation` | 257 | Latin, Greek, general, supplemental, CJK and fullwidth punctuation |
| `letters` | 1,312 | Latin (with extensions and IPA), Greek, Cyrillic, Hebrew and Arabic letters |
| `symbols` | 2,273 | letterlike symbols, technical, geometric shapes, box drawing, dingbats, chess, cards, … |
| `hieroglyphs` | 1,072 | Egyptian Hieroglyphs |

The rules live in `database/sources/curated/symbols.json`. Controls, format characters (bidi overrides,
zero-width characters, tags), private-use and unassigned code points, separators and standalone combining
marks are never included, in any group: they cannot be seen, and several are the raw material of text
smuggling. Copying from this catalogue never pastes something invisible.

## Usage

```php
$symbols = Emojis::symbols();

$symbols->groups();                  // ['popular', 'arrows', 'currency', …]
$symbols->group('currency');         // list<Symbol>, in code point order
$symbols->characters('arrows');      // ['←', '↑', '→', '↓', …]: the characters alone, for a picker or "copy all"
$symbols->all();                     // list<Symbol>, every one, in code point order
$symbols->get('→');                  // by character, 'U+2192' or '2192'
$symbols->search('double arrow');    // every word must match; exact name, then name prefix, first
$symbols->search('sign', 'currency', limit: 10);          // limit: 0 returns every match
```

## `Symbol`

| Member | `→` |
|---|---|
| `char` | `→` |
| `name`, `label()` | `rightwards arrow`, `Rightwards arrow` |
| `unicode()` | `U+2192` |
| `htmlEntity()` | `&rarr;` — the shortest WHATWG named entity, else `&#x2192;` |
| `css()`, `javascript()`, `php()` | `\2192`, `\u2192`, `\u{2192}` |
| `category`, `block` | `Sm`, `Arrows` |

A `Symbol` is `JsonSerializable`, so `response()->json(Emojis::symbols()->group('arrows'))` feeds a picker.

`database/generated/symbols.php` is readable as it stands: each record starts with its character
(`2192 => ['→', 'rightwards arrow', 'Sm', 0, '&rarr;']`), and each group lists its characters,
space-separated.

## Coverage

`.dev/tools/cross-check.php` checks every character copychar.cc lists: each is one of these symbols, an emoji,
or one of the invisible characters excluded above.

---

[← Docs index](../../README.md#documentation)
