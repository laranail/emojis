# Extending

Six seams let an application reshape the package without editing it.

| Seam | Use it for |
|---|---|
| `addCustom()` | image-only emoji Unicode does not have (`:laravel:`) |
| `addShortcode()`, `addEmoticon()` | extra vocabulary for existing emoji |
| `addImageSet()` | another image source, or a self-hosted one |
| `through()` on a converter | post-processing each rendered emoji |
| `Emojis::macro()`, `EmojiCollection::macro()` | extra methods |
| container binding of `FailureReporter`, `TerminalProbe`, `HtmlFactory` | replacing a collaborator |

## Custom emoji

```php
// a service provider's boot(), or config('laranail.emojis.custom')
Emojis::addCustom('laravel', 'https://example.com/laravel.svg', aliases: ['lara'], label: 'Laravel');

Emojis::text('Built with :laravel:')->toImages();   // <img … src="https://example.com/laravel.svg">
Emojis::text('Built with :laravel:')->toEmoji();    // unchanged: no Unicode form
```

Names must match `[a-z0-9_+-]+` and must not shadow a Unicode shortcode. URLs must be `https://`,
root-relative or a `data:image/…;base64` URI; anything else throws. Pass `fallback:` to give text modes a
Unicode stand-in.

## Extra shortcodes and emoticons

```php
Emojis::addShortcode('shipit', 'rocket');
Emojis::addEmoticon(':3', 'cat face');
```

## Macros

```php
use Simtabi\Laranail\Emojis\Core\Emojis;

Emojis::macro('party', fn (string $text): string => $this->text($text . ' :tada:')->toEmoji());
```

Macros are process-global, like Laravel's: register them at boot, and in tests call `flushMacros()`.

## Replacing collaborators

In Laravel, rebind before the first resolution:

| Contract | Default | Replace to |
|---|---|---|
| `Core\Contracts\FailureReporter` | `LaravelFailureReporter` (package-tools `FailurePolicy`) | route failures elsewhere |
| `Core\Contracts\TerminalProbe` | `ConsoleTerminalProbe` | force terminal behaviour |
| `Core\Contracts\HtmlFactory` | `IlluminateHtmlFactory` (`HtmlString`) | another safe-HTML type |

For tests, swap the whole instance: `app()->instance(Emojis::class, Emojis::create([...]))`.

## Freezing

Every registration is frozen once the application has booted, and throws afterwards. Register in a
service provider.

---

[← Docs index](../../README.md#documentation)
