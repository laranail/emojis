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

Nothing four-byte is ever stored: each emoji becomes its ASCII code, any other character above U+FFFF
(an emoji newer than the dataset, a rare ideograph) becomes `:U+1FC00:`, and a `:` or `\` the user typed is
escaped, so everything reads back exactly as written. For a single emoji per column, `AsEmoji` stores the
hexcode instead. See [Laravel integration](../tools/laravel.md#casts).

---

[← Docs index](../../README.md#documentation)
