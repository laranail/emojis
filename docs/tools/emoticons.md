# Emoticons

Every ASCII smiley the scanner recognises, grouped by the emoji it converts to.

## How to read the table

- **Emoticons** match as whole words once a caller opts in with `withEmoticons()`. The one in bold is the
  emoji's *primary* emoticon: what `toEmoticons()` writes back.
- **Opt-in only** entries collide with ordinary prose and code (`XD`, `8)`, `o_O`), so they match only with
  `withEmoticons(risky: true)`. Anything that starts with a letter or a digit is in this column
  automatically; `:/`, `:|`, `(:`, and the prose-like `(y)`, `(n)`, `:?` and `<><` are added by hand. An
  emoji's primary can still be one of these: `toEmoticons()` writes `(y)` for 👍.
- Each emoticon converts to exactly one emoji. Where sources disagree, the curated overlay wins, then
  emojibase, Google's emoji metadata and iamcal, in that order ([data sources](data-sources.md)).

```php
Emojis::text('Nice one :] ^^')->withEmoticons()->toEmoji();          // 'Nice one 🙂 😊'
Emojis::text('o_O T_T')->withEmoticons(risky: true)->toEmoji();      // '🤨 😭'
Emojis::fromEmoticon('>_<');                                         // Emoji 😣
Emojis::get('🙂')->emoticons();                                      // ['(:', ':)', ':-)', ':-]', ':]', '=]']
```

Add your own with `Emojis::addEmoticon()` ([extending](extending.md)). Text faces with no single emoji
equivalent, such as `¯\_(ツ)_/¯`, are in the [kaomoji](kaomoji.md) catalogue instead.

## Choosing what matches

Some readings are judgement calls, and the package makes one so it can work out of the box. These are
the ones worth a look, and the lever for each:

| Case | Packaged behaviour | If you want otherwise |
|---|---|---|
| `(y)` and `(n)` | opt-in only: "Continue? (y) or (n)" stays text | `addEmoticon('(y)', 'thumbs up')` for just that one |
| `:?` and `<><` | opt-in only: they read as punctuation or arrows | `addEmoticon(':?', …)` |
| `:X` | 😘 face blowing a kiss, as the upstream sources have it | `addEmoticon(':X', 'zipper-mouth face')` |
| `:O` and `:o` | 😮 and 😲, from different sources | `addEmoticon(':o', 'face with open mouth')` |
| 😎 😇 👋 😵 🤨 | every emoticon for them starts with a letter or digit, so they are opt-in only and `toEmoticons()` writes a shortcode | `withEmoticons(risky: true)`, or add the one you use |
| a default you do not want | matched | `removeEmoticon(':P')`, or `input.disabled_emoticons` in config |

The levers, from broadest to narrowest:

- `withEmoticons(risky: true)` matches every opt-in-only emoticon, per call.
- `addEmoticon()` (or `extend.emoticons` in config) adds or remaps one. Added emoticons always match, even
  text the package marks opt-in only.
- `removeEmoticon()` (or `input.disabled_emoticons`) switches one off everywhere: matching, `emoticons()`,
  `fromEmoticon()` and `toEmoticons()`. An emoji whose primary is removed is written with another of its
  emoticons, or degrades to a shortcode.

```php
Emojis::addEmoticon('(y)', 'thumbs up')->text('ok (y) (n)')->withEmoticons()->toEmoji();   // 'ok 👍 (n)'
Emojis::removeEmoticon(':)')->text('🙂')->toEmoticons();                                     // ':-)'
```

## The list

Generated from the dataset by `.dev/tools/emoticons-doc.php`; `composer sync-check` fails when it drifts.

<!-- emoticons:start -->
| Emoji | Name | Emoticons | Opt-in only |
|---|---|---|---|
| 😀 | grinning face | **`:D`** `:-D` `=D` | — |
| 😃 | grinning face with big eyes | **`=)`** `=-)` | — |
| 😄 | grinning face with smiling eyes | **`:))`** `:-))` | `C:` `c:` |
| 😁 | beaming face with smiling eyes | **`*^_^*`** | — |
| 😆 | grinning squinting face | **`:->`** | `X-D` `XD` `xD` |
| 😅 | grinning face with sweat | **`^_^;`** | — |
| 🤣 | rolling on the floor laughing | **`:'D`** `*>w<*` | — |
| 😂 | face with tears of joy | **`:')`** `:'-)` `>w<` | — |
| 🙂 | slightly smiling face | **`:)`** `:-)` `:-]` `:]` `=]` | `(:` |
| 😉 | winking face | **`;)`** `;-)` `;D` | — |
| 😊 | smiling face with smiling eyes | **`^_^`** `:>` `^.^` `^^` | — |
| 😇 | smiling face with halo | — | `0:)` `0:-)` `O:)` `O:-)` `o:)` |
| 🥰 | smiling face with hearts | **`<3:)`** | — |
| 🤩 | star-struck | **`*_*`** `*-*` | — |
| 😘 | face blowing a kiss | **`:*`** `:-*` `:-X` `:X` `:x` `;*` | — |
| 😚 | kissing face with closed eyes | **`:**`** | — |
| 😙 | kissing face with smiling eyes | **`^3^`** | — |
| 🥲 | smiling face with tear | **`:,)`** | — |
| 😛 | face with tongue | **`:P`** `:-P` `:-b` `:-p` `:b` `:p` `=P` | — |
| 😜 | winking face with tongue | **`;p`** `;-P` `;-b` `;-p` `;P` `;b` | — |
| 😝 | squinting face with tongue | **`>q<`** | `XP` `xP` `xp` |
| 🤑 | money-mouth face | **`$_$`** | — |
| 🤗 | smiling face with open hands | **`\(^o^)/`** | — |
| 🤔 | thinking face | **`:l`** `:L` `=L` | — |
| 🤐 | zipper-mouth face | **`:z`** `:Z` | — |
| 🤨 | face with raised eyebrow | — | `O.o` `O_o` `o.O` `o_O` |
| 😐 | neutral face | **`:-\|`** | `:\|` |
| 😑 | expressionless face | **`-_-`** `-.-` | — |
| 😶 | face without mouth | **`:#`** | — |
| 😏 | smirking face | **`:j`** `>~>` | — |
| 😒 | unamused face | `>->` | `:?` |
| 😬 | grimacing face | — | `8D` |
| 😔 | pensive face | **`._.`** | — |
| 😪 | sleepy face | **`(-.-)zzZZ`** | — |
| 😴 | sleeping face | — | `Z_Z` |
| 🤢 | nauseated face | **`%(`** `:-###` | — |
| 🤮 | face vomiting | **`:-O##`** | — |
| 🥴 | woozy face | **`:&`** | — |
| 😵 | face with crossed-out eyes | — | `XO` `X_X` `X_o` `x_x` `xo` |
| 🤠 | cowboy hat face | **`<):)`** | — |
| 😎 | smiling face with sunglasses | — | `8)` `8-)` `B)` `B-)` |
| 🤓 | nerd face | **`:B`** `:-B` | — |
| 🧐 | face with monocle | — | `o~O` |
| 😕 | confused face | **`:-/`** `:-\` `:\` | `:/` |
| 🙁 | slightly frowning face | **`:(`** `:-(` `:-[` `:-{` `:[` `:{` `=(` | — |
| 😮 | face with open mouth | **`:O`** `:-O` `:-o` | — |
| 😲 | astonished face | **`:o`** | — |
| 😳 | flushed face | **`:$`** `:-$` | `8-0` `O_O` `o_o` |
| 😦 | frowning face with open mouth | — | `D=` |
| 😧 | anguished face | **`:s`** `:-S` `:S` | — |
| 😨 | fearful face | — | `D-:` |
| 😰 | anxious face with sweat | — | `D-':` |
| 😢 | crying face | **`:'(`** `:'-(` `:((` `;(` | — |
| 😭 | loudly crying face | **`:'o`** `;-;` `;_;` | `T.T` `T_T` |
| 😱 | face screaming in fear | **`@0@`** | `Dx` |
| 😖 | confounded face | **`>:[`** | `X(` `x(` |
| 😣 | persevering face | **`>.<`** `>_<` | — |
| 😞 | disappointed face | **`):`** | — |
| 😓 | downcast face with sweat | **`:<`** `-_-;` | — |
| 😩 | weary face | — | `D:` |
| 😫 | tired face | **`:c`** `:C` | `D-X` |
| 🥱 | yawning face | **`~O~`** | — |
| 😡 | enraged face | **`>:/`** `>:O` | — |
| 😠 | angry face | **`>:(`** `>:-(` | `X-(` |
| 🤬 | face with symbols on mouth | **`:@`** `#$@!` `:-@` | — |
| 😈 | smiling face with horns | **`>:)`** `>:-D` `>:D` | `3:)` |
| 👿 | angry face with horns | — | `3:(` |
| 🤡 | clown face | **`:o)`** | — |
| 👹 | ogre | **`>0)`** | — |
| 👽 | alien | **`(<>..<>)`** | — |
| 😽 | kissing cat | **`:3`** `:-3` | — |
| 💕 | two hearts | **`<3<3`** | — |
| ❣️ | heart exclamation | **`<3!`** | — |
| 💔 | broken heart | **`</3`** | — |
| ❤️ | red heart | **`<3`** | — |
| 👋 | waving hand | — | `o/` |
| 🤘 | sign of the horns | **`\m/`** `\M/` | — |
| 👍 | thumbs up | — | `(y)` |
| 👎 | thumbs down | — | `(n)` |
| 🙌 | raising hands | **`\o/`** | — |
| 🧙‍♂️ | man mage | **`:{>`** | — |
| 🧛 | vampire | **`:E`** | — |
| 🧟 | zombie | — | `8#` |
| 💏 | kiss | **`(-}{-)`** | — |
| 🐱 | cat face | **`=^.^=`** | — |
| 🐮 | cow face | — | `3:O` |
| 🐁 | mouse | **`<:3)~`** | — |
| 🐧 | penguin | **`<(")`** | — |
| 🐟 | fish | — | `<><` |
| 🌹 | rose | **`@-,-'-,-`** | — |
| 🌚 | new moon face | **`>_>`** | — |
| 🌝 | full moon face | **`<_<`** | — |
<!-- emoticons:end -->

---

[← Docs index](../../README.md#documentation)
