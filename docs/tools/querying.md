# Querying and search

`Query` is an immutable filter over the catalogue; nothing runs until `get()`, `first()` or `count()`.

## Filters

```php
use Simtabi\Laranail\Emojis\Core\Enums\{Group, Subgroup, EmojiVersion, SequenceType};

Emojis::query()
    ->group(Group::AnimalsAndNature)
    ->supportedBy(EmojiVersion::V13_0)
    ->limit(20)
    ->get();
```

| Method | Keeps |
|---|---|
| `group(Group ...)`, `subgroup(Subgroup ...)` | those groups |
| `type(SequenceType ...)`, `flags()` | those sequence kinds |
| `supportedBy(EmojiVersion)` | emoji a platform at that version can display |
| `since(EmojiVersion)` | emoji introduced in that version or later |
| `skinToneable(bool)` | emoji that do (or do not) take a skin tone |
| `withTextPresentation(bool)` | emoji with (or without) a VS15 form |
| `withSkinToneVariants()`, `withComponents()` | include what is excluded by default |
| `limit()`, `offset()` | paging |

Skin-tone variants and the nine components are excluded unless asked for: a picker wants one waving hand,
not six.

## Search

```php
Emojis::search('cat');                           // 24 results by default
Emojis::query()->search('fusée', 'fr')->first(); // 🚀
```

Results are ranked, then kept in CLDR order within a rank:

| Rank | Match |
|---|---|
| 0 | exact name (localized or English) |
| 1 | name prefix |
| 2 | exact shortcode or slug (`thumbs up` matches `thumbs_up`) |
| 3 | exact keyword |
| 4 | keyword prefix |
| 5 | substring of a name or shortcode |

## `EmojiCollection`

Immutable and iterable: `all()`, `first()`, `last()`, `count()`, `isEmpty()`, `filter()`, `map()`, `take()`,
`skip()`, `unique()`, `groupByGroup()`, `hexcodes()`, `render(Mode, $separator)`. It is JSON-serializable
and macroable.

---

[← Docs index](../../README.md#documentation)
