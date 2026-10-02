# Laravel integration

`EmojisServiceProvider` binds one `Emojis` per worker and adds a facade, a helper, Blade, casts, rules and commands.

## Names

Every name is vendor-scoped and asserted against Laravel's live registries in `NamingConventionTest`:

| Surface | Name |
|---|---|
| Config | `config('laranail.emojis.*')`, published to `config/laranail/emojis.php` |
| Publish tags | `laranail::emojis-config`, `laranail::emojis-assets` (built CSS/JS → `public/vendor/laranail/emojis`) |
| Translations | `laranail/emojis::validation.*` |
| Blade directive | `@laranailEmojis(...)` |
| Blade components | `<x-laranail-emojis::emoji />`, `<x-laranail-emojis::styles />` |
| Commands | `laranail::emojis.search`, `.show`, `.convert`, `.export`, `.sanitize`, `.images` |

## Facade and helper

```php
use Simtabi\Laranail\Emojis\Facades\Emojis;
use function Simtabi\Laranail\Emojis\emoji;

Emojis::text('hi :wave:')->toEmoji();
emoji('wave');                          // Emoji
emoji()->search('cat');                 // EmojisFluent — typed, so IDEs complete it
```

The helper is namespaced, so it cannot collide with another package's global `emoji()`.

## Blade

```blade
<x-laranail-emojis::emoji name="wave" />
<x-laranail-emojis::emoji name="wave" skin-tone="medium-dark" />
<x-laranail-emojis::emoji name="rocket" mode="image" set="openmoji" fit="tight" locale="fr" />
<x-laranail-emojis::styles />                {{-- once, in <head>; `link` for the published file --}}

@laranailEmojis($comment->body)             {{-- images; text escaped --}}
@laranailEmojis($comment->body, 'emoji')    {{-- native emoji; text escaped --}}
```

The component wraps native output in `<span class="laranail-emoji laranail-emoji-native" role="img" aria-label="…">`. `toHtml()` returns an
`HtmlString`, so `{{ Emojis::text($x)->toHtml() }}` is not escaped twice. The directive compiles to a call
to `Laravel\BladeDirective`, so a cached view holds only a class name.

## Validation

```php
use Simtabi\Laranail\Emojis\Laravel\Rules\{NoEmoji, ContainsEmoji, OnlyEmoji, SingleEmoji, MaxEmojis, NoHiddenCharacters, EmojiPolicyRule};

$request->validate([
    'username' => ['required', new NoEmoji],
    'reaction' => ['required', new SingleEmoji],
    'bio' => ['nullable', new MaxEmojis(5), new EmojiPolicyRule],
    'handle' => ['required', new NoHiddenCharacters],
]);
```

`NoEmoji` and `MaxEmojis` count emoji the dataset does not know yet, so a newer emoji cannot slip past. Non-
string values and invalid UTF-8 fail. With `laranail/validation`, pass them through `->rule()`:
`FluentRule::string()->rule(new NoEmoji)`.

`EmojiImageRule` accepts an image a user supplies — an uploaded file, a data URI or an `https://` URL — under
the `images.custom` limits. Store what `Emojis::image($value)` returns rather than the raw input, so an SVG is
kept in its sanitised form:

```php
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;
use Simtabi\Laranail\Emojis\Laravel\Rules\EmojiImageRule;

$request->validate(['icon' => ['required', new EmojiImageRule]]);

$icon = $request->hasFile('icon')
    ? EmojiImage::fromFile($request->file('icon')->getRealPath(), Emojis::options()->imagePolicy)
    : Emojis::image($request->input('icon'));   // a data URI or https URL

$user->update(['icon' => $icon->src]);
```

## Casts

```php
use Simtabi\Laranail\Emojis\Laravel\Casts\{AsEmoji, AsEmojiText};

protected function casts(): array
{
    return [
        'reaction' => AsEmoji::class,       // stored as "1F44B-1F3FD", read as Emoji
        'bio' => AsEmojiText::class,        // stored as ":wave_tone3:", read as "👋🏽"
    ];
}
```

`AsEmojiText` stores no four-byte character, for utf8mb3 columns, and reads every value back exactly as it
was written. Its stored form is a small escaped format:

| Written | Stored |
|---|---|
| an emoji | `:rocket:` — its ASCII code, fixed delimiters whatever `shortcodes.delimiters` says |
| `:` | `\:` |
| `\` | `\\` |
| another character above U+FFFF | `:U+1FC00:` |

So `a🚀b` is `a:rocket:b`, and a shortcode the user typed (`:rocket:`) is `\:rocket\:`, which reads back as
text. Rows written by 0.1 — emoji as shortcodes, nothing escaped — read back as before, except that a
shortcode touching a letter is now converted too. `AsEmojiText::encode()` and `decode()` are public, for
migrating or searching stored values.

## Commands

```bash
php artisan laranail::emojis.search cat --locale=fr
php artisan laranail::emojis.show wave
php artisan laranail::emojis.convert "Ship it :) :rocket:" --to=ascii --from=unicode,shortcode,emoticon
echo "hi 🚀" | php artisan laranail::emojis.convert - --to=shortcode
php artisan laranail::emojis.export public/emojis.json --locale=fr --variants
php artisan laranail::emojis.export public/emojis.json --with=tags,emoticons    # or kaomoji, symbols, all
php artisan laranail::emojis.convert "✅ Deployed" --to=tag                     # [OK] Deployed
php artisan laranail::emojis.sanitize - --check < user-content.txt
php artisan laranail::emojis.images install twemoji
php artisan laranail::emojis.images verify twemoji
```

## HTTP API

A read-only JSON API over the catalogue, off by default — see [HTTP API](api.md).

## Health

The provider registers a doctor check (`php artisan laranail::package-tools.doctor`) and an `about`
section. A configured image set that does not exist stops boot; a degraded locale, and a set configured for
local serving that is not installed yet, show in both.

---

[← Docs index](../../README.md#documentation)
