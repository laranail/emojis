# Sanitise user input

Remove or reject emoji in fields that must be plain text, such as usernames and slugs.

```php
use Simtabi\Laranail\Emojis\Facades\Emojis;
use Simtabi\Laranail\Emojis\Laravel\Rules\NoEmoji;

$request->validate(['username' => ['required', 'string', new NoEmoji]]);   // reject

$clean = Emojis::text($request->input('title'))->strip(collapseWhitespace: true);   // or remove
```

Both catch emoji newer than the dataset and stray modifiers, and neither removes a zero-width joiner that
belongs to an Indic or Persian word. See [text conversion](../tools/conversion.md#inspecting).

---

[← Docs index](../../README.md#documentation)
