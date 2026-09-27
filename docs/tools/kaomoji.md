# Kaomoji

2,092 text faces — Google's 468 in 15 groups, and 1,624 Japanese kaomoji (顔文字) in 23 groups with tags and kana readings.

## Usage

```php
Emojis::kaomojiGroups();                    // ['classic' => 'Classic', …, 'ja_cute' => '可愛い', …]
Emojis::kaomoji('table_flipping');          // list<Kaomoji>
Emojis::kaomoji(asciiOnly: true);           // only seven-bit faces
Emojis::searchKaomoji('ねこ');               // by kana reading, tag, description or group
Emojis::searchKaomoji('shrug');
```

Each `Kaomoji` has `value`, `group`, `description`, `isAscii`, `tags` and `readings`, is `Stringable` and
JSON-serializable.

## Groups

From Google's emoji metadata (English): Classic, Smiling, Loving, Hugging, Flexing, Animals, Surprising,
Dancing, Shrugging, Table Flipping, Disapproving, Crying, Worrying, Pointing, Sparkling.

From kaomojikan (Japanese, prefixed `ja_`): 可愛い (`ja_cute`), 泣く (`ja_cry`), 動物 (`ja_animal`), and the other
categories of that collection. Only one-line faces are included; its multi-line ASCII art cannot sit in
running text. A face both sources carry keeps Google's group and description.

Kaomoji are a catalogue, not a conversion target: they have no one-to-one mapping to emoji.

---

[← Docs index](../../README.md#documentation)
