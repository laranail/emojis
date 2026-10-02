# Use your own emoji images

Give an emoji your brand's picture, or add one Unicode does not have, from a URL, base64 or a file.

```php
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;

// a service provider's boot()
Emojis::useImage('thumbsup', 'https://cdn.example.com/emoji/thumbs.png');
Emojis::addCustom('shipit', EmojiImage::fromFile(resource_path('emoji/shipit.svg')), aliases: ['ship_it']);
Emojis::addCustom('partyparrot', 'data:image/webp;base64,UklGRi…');

Emojis::text('Great work 👍 :shipit:')->toImages();
```

Each image is size-, type- and dimension-checked, and SVG is sanitised; see
[images](../tools/images.md#your-own-images). The same entries can live in config under `extend.images` and
`extend.custom`.

---

[← Docs index](../../README.md#documentation)
