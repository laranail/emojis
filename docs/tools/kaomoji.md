# Kaomoji

470 text faces in 15 groups, from Google's emoji metadata — ASCII ones like `:-)` and others like `¯\_(ツ)_/¯`.

## Usage

```php
Emojis::kaomojiGroups();                    // ['classic' => 'Classic', 'shrugging' => 'Shrugging', …]
Emojis::kaomoji('table_flipping');          // list<Kaomoji>
Emojis::kaomoji(asciiOnly: true);           // only seven-bit faces
```

Each `Kaomoji` has `value`, `group`, `description` and `isAscii`, is `Stringable` and JSON-serializable.

## Groups

Classic, Smiling, Loving, Hugging, Flexing, Animals, Surprising, Dancing, Shrugging, Table Flipping,
Disapproving, Crying, Worrying, Pointing, Sparkling.

Kaomoji are a catalogue, not a conversion target: they have no one-to-one mapping to emoji.

---

[← Docs index](../../README.md#documentation)
