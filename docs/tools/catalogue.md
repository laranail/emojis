# Catalogue and lookups

`Emojis` (`Simtabi\Laranail\Emojis\Core\Emojis`, the `Emojis` facade, `emoji()` helper) finds emoji; each result is an immutable `Emoji`.

## Finding an emoji

| Call | Returns | Accepts |
|---|---|---|
| `get($key)` | `Emoji`, or throws `EmojiNotFound` | anything `find()` accepts |
| `find($key)` | `?Emoji` | `EmojiId`, `Emoji`, character (any qualification), hexcode, `U+` code point, shortcode with or without colons, slug, emoticon, English CLDR name |
| `fromChar()`, `fromHexcode()`, `fromShortcode()`, `fromEmoticon()` | `?Emoji` | one kind only |
| `flag($region)` | `Emoji` | ISO 3166-1 alpha-2 (`'KE'`), `'EU'`, `'UN'`, and the three RGI subdivisions (`'GB-ENG'`, `'GB-SCT'`, `'GB-WLS'`) |
| `all()` | `EmojiCollection` | every emoji and component, CLDR order |
| `random(?Group)` | `Emoji` | |

```php
Emojis::get('☺');                  // accepts the unqualified form, returns ☺️ (263A-FE0F)
Emojis::get('man: red hair');      // English CLDR name
Emojis::flag('UK');                // throws: UK is not an ISO region; use GB
```

## `EmojiId`

A generated backed enum with one case per emoji that is not a skin-tone variant (1,932 cases), valued by
hexcode: `EmojiId::GrinningFace`, `EmojiId::WavingHand`, `EmojiId::FlagKenya`. Names starting with a digit
are prefixed with `E` (`EmojiId::E1stPlaceMedal`). Case names are frozen once published.

## `Emoji`

| Property | Type | Example (`👋🏽`) |
|---|---|---|
| `char` | `string` | `👋🏽` |
| `hexcode` | `string` | `1F44B-1F3FD` |
| `codepoints` | `list<int>` | `[0x1F44B, 0x1F3FD]` |
| `englishName` | `string` | `waving hand: medium skin tone` |
| `slug` | `string` | `waving_hand_medium_skin_tone` |
| `asciiCode` | `string` | `wave_tone3` |
| `group`, `subgroup` | `Group`, `Subgroup` | `PeopleAndBody`, `HandFingersOpen` |
| `version` | `EmojiVersion` | `V1_0` |
| `type` | `SequenceType` | `Modifier` |
| `tones` | `list<SkinTone>` | `[SkinTone::Medium]` |
| `baseHexcode` | `?string` | `1F44B` |
| `hasTextPresentation` | `bool` | `false` |
| `region` | `?string` | for flags: `KE`, `GBSCT` |
| `gender`, `hair`, `direction` | `?string` | for ZWJ sequences |

| Method | Returns |
|---|---|
| `name(?locale)`, `keywords(?locale)` | CLDR name and keywords, English fallback |
| `shortcode(?preset)`, `shortcodes(?preset)`, `allShortcodes()` | shortcodes, primary first |
| `emoticon()`, `emoticons()` | primary emoticon, every emoticon |
| `withSkinTone(SkinTone ...$perPerson)`, `withoutSkinTone()`, `base()`, `skinToneVariants()` | variants |
| `supportsSkinTones()`, `skinTonePeople()`, `isFlag()`, `isSkinToneVariant()`, `is($other)` | checks |
| `render(Mode, ?EscapeFormat)`, `toText()`, `toAscii()`, `toHtmlEntity()`, `toCodepoints()`, `escaped()` | one form |
| `imageUrl(?set)`, `toImage(?set)` | image URL, `<img>` markup |

## Skin tones

One tone applies to everyone; two-person emoji take one tone per person, in sequence order.

```php
Emojis::get('handshake')->withSkinTone(SkinTone::Light, SkinTone::Dark);   // 🫱🏻‍🫲🏿
Emojis::get('handshake')->withSkinTone(SkinTone::Medium);                  // 🤝🏽
```

Unicode encodes "both the same" as a single modifier, so equal tones collapse to the single form.

---

[← Docs index](../../README.md#documentation)
