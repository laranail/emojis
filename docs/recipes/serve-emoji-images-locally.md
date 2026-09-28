# Serve emoji images from your own origin

Stop sending visitors' IP addresses to a CDN, and keep `img-src` at `'self'`.

```bash
php artisan laranail::emojis.images install twemoji
```

```php
// config/laranail/emojis.php
'images' => ['set' => 'twemoji', 'source' => 'local'],
```

Add `php artisan laranail::emojis.images verify twemoji` to your deploy checks; it fails if any installed
file has changed. See [images](../tools/images.md#serving-from-your-own-origin).

---

[← Docs index](../../README.md#documentation)
