# Store emoji in a utf8mb3 column

Keep emoji in MySQL columns whose charset cannot hold four-byte characters.

```php
use Simtabi\Laranail\Emojis\Laravel\Casts\AsEmojiText;

protected function casts(): array
{
    return ['bio' => AsEmojiText::class];
}

$user->bio = 'Coffee first ☕🚀';      // stored as "Coffee first :coffee::rocket:"
$user->bio;                             // "Coffee first ☕🚀"
```

The stored form uses `Mode::Ascii`, which is seven-bit for every emoji in the dataset. For a single emoji
per column, `AsEmoji` stores the hexcode instead. See [Laravel integration](../tools/laravel.md#casts).

---

[← Docs index](../../README.md#documentation)
