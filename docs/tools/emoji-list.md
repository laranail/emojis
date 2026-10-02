# Emoji list

All 1,932 emoji, one page per Unicode group; with their skin-tone variants the catalogue holds 3,972.

Each page gives the emoji, its CLDR name, its shortcode and the Unicode version that added it. Skin-tone
variants are not listed separately: the *Skin tones* column says whether an emoji takes them, and
`Emoji::skinToneVariants()` returns them. Generated from the dataset by `.dev/tools/emoji-list-doc.php`;
`composer sync-check` fails when it drifts.

| Group | Emoji | First few |
|---|---|---|
| [Smileys & Emotion](emoji-list-smileys-and-emotion.md) | 172 | 😀 😃 😄 😁 😆 😅 🤣 😂 |
| [People & Body](emoji-list-people-and-body.md) | 390 | 👋 🤚 🖐️ ✋ 🖖 🫱 🫲 🫳 |
| [Component](emoji-list-component.md) | 9 | 🏻 🏼 🏽 🏾 🏿 🦰 🦱 🦳 |
| [Animals & Nature](emoji-list-animals-and-nature.md) | 161 | 🐵 🐒 🦍 🦧 🐶 🐕 🦮 🐕‍🦺 |
| [Food & Drink](emoji-list-food-and-drink.md) | 132 | 🍇 🍈 🍉 🍊 🍋 🍋‍🟩 🍌 🍍 |
| [Travel & Places](emoji-list-travel-and-places.md) | 221 | 🌍 🌎 🌏 🌐 🗺️ 🗾 🧭 🏔️ |
| [Activities](emoji-list-activities.md) | 85 | 🎃 🎄 🎆 🎇 🧨 ✨ 🎈 🎉 |
| [Objects](emoji-list-objects.md) | 268 | 👓 🕶️ 🥽 🥼 🦺 👔 👕 👖 |
| [Symbols](emoji-list-symbols.md) | 224 | 🏧 🚮 🚰 ♿ 🚹 🚺 🚻 🚼 |
| [Flags](emoji-list-flags.md) | 270 | 🏁 🚩 🎌 🏴 🏳️ 🏳️‍🌈 🏳️‍⚧️ 🏴‍☠️ |

In code: `Emojis::all()` returns them all, `Emojis::query()->group(Group::SmileysAndEmotion)` one group,
and `laranail::emojis.export` writes them as JSON. The [catalogue](catalogue.md) describes each field.

---

[← Docs index](../../README.md#documentation)
