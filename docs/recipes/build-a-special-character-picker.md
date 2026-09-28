# Build a special-character picker

Serve the symbol groups as JSON for a copy-to-clipboard picker.

```php
Route::get('/symbols/{group}', fn (string $group) => response()->json(Emojis::symbols()->group($group)));
Route::get('/symbols', fn (Request $r) => response()->json(Emojis::symbols()->search((string) $r->query('q'))));
```

Each item carries `char`, `unicode`, `name`, `html` and `css`. Nothing invisible is ever in the list, so a
user cannot copy a bidi override or a zero-width character from it. See [symbols](../tools/symbols.md).

---

[← Docs index](../../README.md#documentation)
