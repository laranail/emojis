# Feed a JavaScript emoji picker

Serve the catalogue to a browser picker as one versioned JSON file.

```bash
php artisan laranail::emojis.export public/emojis.fr.json --locale=fr
```

The document has `schemaVersion`, `dataset`, `locale` and `emojis`; each entry has `emoji`, `hexcode`,
`name`, `keywords`, `shortcodes`, `emoticons`, `group`, `subgroup`, `version` and `skins` (tone key →
hexcode). Entries are in CLDR order, which is the order pickers use. Add `--variants` to include every
skin-tone variant as its own entry. Offsets from `extract()` are UTF-8 bytes; use
`EmojiMatch::utf16Offset()` for JavaScript string indices.

---

[← Docs index](../../README.md#documentation)
