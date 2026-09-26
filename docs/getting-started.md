# Getting started

Five calls cover most uses: look an emoji up, convert text, sanitise text, search, and render for a page.

## Look one up

```php
use Simtabi\Laranail\Emojis\Facades\Emojis;       // or Emojis::create() outside Laravel
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiId;

$wave = Emojis::get('wave');                        // by shortcode — or '👋', '1F44B', 'waving_hand', 'o/'
$wave = Emojis::get(EmojiId::WavingHand);           // typed and IDE-completed

$wave->name();                                      // "waving hand" (in the app locale)
$wave->withSkinTone(SkinTone::Medium);              // 👋🏽
$wave->shortcode();                                 // "wave"
$wave->imageUrl();                                  // Twemoji SVG URL
```

`get()` throws `EmojiNotFound`; `find()` returns `null`.

## Convert text

```php
use Simtabi\Laranail\Emojis\Core\Enums\Mode;

Emojis::text('Ship it :rocket: :)')->withEmoticons()->toEmoji();   // "Ship it 🚀 🙂"
Emojis::text('Ship it 🚀')->toAscii();                              // "Ship it :rocket:"
Emojis::text('Ship it 🚀')->to(Mode::Name);                         // "Ship it [rocket]"
```

Every mode converts to every other; see [modes and conversion](modes.md).

## Sanitise

```php
Emojis::strip('Great work 🎉👏🏽');        // "Great work "
Emojis::contains('plain text');           // false
```

`strip()` removes emoji the dataset does not know yet as well (future emoji, vendor sequences, stray
modifiers). For forms, use the `NoEmoji` rule — see [Laravel integration](tools/laravel.md#validation).

## Search

```php
Emojis::search('cat');                    // 🐈 😹 😼 🐱 … ranked
Emojis::search('chat', 'fr');             // French names and keywords
```

## Render for a page

```blade
<x-laranail-emojis::emoji name="wave" />                    {{-- native, with an accessible label --}}
<x-laranail-emojis::emoji name="wave" mode="image" />       {{-- Twemoji <img> --}}
@laranailEmojis($comment->body)                             {{-- the whole comment, text escaped --}}
```

---

[← Docs index](../README.md#documentation)
