# Japanese emoji

Emoji began in Japan; this page covers the carriers' original emoji, Japanese names, the Japanese collection, and kaomoji.

## Carrier emoji (docomo, au, SoftBank)

Before Unicode, each Japanese carrier had its own emoji set. Old data still carries them as private-use code
points: docomo U+E63E–E757, au (KDDI) U+E468–EB88, SoftBank U+E001–E537, and Google's bridge mapping in
U+FE000 onwards. The three carrier ranges overlap, so reading one always names the carrier.

```php
use Simtabi\Laranail\Emojis\Core\Enums\{Carrier, Mode};

Emojis::text($legacy)->carrier(Carrier::Docomo)->from(Mode::Carrier)->toEmoji();   // private-use → Unicode
Emojis::text('☀️')->carrier(Carrier::SoftBank)->to(Mode::Carrier);                // Unicode → SoftBank code
Emojis::get('sunny')->carrierCode(Carrier::Au);
```

| Carrier | Emoji mapped | Distinct codes |
|---|---:|---:|
| docomo | 399 | 245 |
| au | 686 | 630 |
| SoftBank | 470 | 470 |
| Google | 720 | 719 |

Several Unicode emoji share one docomo code (docomo had fewer glyphs); which emoji a shared code *reads as*
follows Unicode's `EmojiSources.txt`, so docomo's U+E63E is the sun ☀️, not the sunrise that shares it.
Emoji a carrier never had degrade to Unicode when writing. Every code round-trips (carrier → emoji →
carrier) — the test suite checks all of them.

PHP's own `SJIS-Mobile#DOCOMO`, `#KDDI` and `#SOFTBANK` encodings already decode Shift-JIS carrier emoji to
Unicode; the private-use form is what survives in databases and mail archives written before that.

## Japanese names and search

`ja` ships with the CLDR names and keywords:

```php
Emojis::get('🈁')->name('ja');            // "ココのマーク"
Emojis::search('寿司', 'ja')->first();     // 🍣
```

## The Japanese collection

```php
Emojis::collection('japanese');                              // 55 emoji
Emojis::query()->inCollection('japanese')->search('castle')->first();  // 🏯
```

The 17 Japanese-text buttons (🈁 🈂️ 🈷️ 🈶 🈯 🉐 🈹 🈚 🈲 🉑 🈸 🈴 🈳 ㊗️ ㊙️ 🈺 🈵), then Japanese-origin symbols
(🔰 💮 🗾 🎌), places (🗻 🗼 🏯 🏣 ⛩️), culture (🎎 🎏 🎐 🎑 🎋 🎍 🏮 👘 🪭 👹 👺 🀄 🎴 💴) and food
(🍱 🍙 🍘 🍥 🍡 🍢 🍣 🍜 🍛 🍚 🍠 🍧 🍶 🍵).

## Kaomoji

1,624 Japanese kaomoji (顔文字) with their tags and kana readings, from kaomojikan, alongside Google's 468
faces — searchable in Japanese: `Emojis::searchKaomoji('ねこ')`. See [kaomoji](kaomoji.md).

---

[← Docs index](../../README.md#documentation)
