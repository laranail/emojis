# Configure the emoji picker

Set the picker up once in config, then let each picker on the page name only what differs.

```php
// config/laranail/emojis.php
'picker' => [
    'features' => ['kaomoji' => true, 'preview' => true, 'recents' => true],
    'placement' => 'top-start',
    'columns' => 9,
    'theme' => 'auto',
    'delivery' => 'auto',
],
```

```blade
<textarea id="reply"></textarea>
<x-laranail-emojis::picker target="#reply" />                                   {{-- the site's defaults --}}
<x-laranail-emojis::picker target="#bio" :features="['recents' => false]" />    {{-- one picker without recents --}}
```

Turn the API on (`LARANAIL_EMOJIS_API=true`) so the payload is fetched and cached once rather than embedded in
every page; `delivery: auto` then uses it. Every key, and which attribute overrides it, is in the
[picker reference](../tools/picker.md#configuration).

---

[← Docs index](../../README.md#documentation)
