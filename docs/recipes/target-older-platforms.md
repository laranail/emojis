# Target older platforms

Avoid sending emoji a recipient's device cannot display, such as in SMS or on old Android releases.

```php
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Facades\Emojis;

Emojis::text('❤️‍🔥 🫩 🚀')->supportedUpTo(EmojiVersion::V13_0)->toEmoji();
// "❤️🔥 :face_with_eye_bags: 🚀"
```

Newer ZWJ sequences decompose into their in-range parts, as a platform without them would show them;
skin-tone variants fall back to their base; anything else degrades to a shortcode. Filter a picker the
same way with `Emojis::query()->supportedBy(EmojiVersion::V13_0)`.

---

[← Docs index](../../README.md#documentation)
