<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Kaomoji;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiId;
use Simtabi\Laranail\Emojis\Core\Catalogue\Query;
use Simtabi\Laranail\Emojis\Core\Symbols\Symbols;
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;
use Simtabi\Laranail\Emojis\Core\Contracts\ImageSet;
use Simtabi\Laranail\Emojis\Core\Text\TextConverter;
use Simtabi\Laranail\Emojis\Core\Emojis as EmojisService;
use Simtabi\Laranail\Emojis\Core\Catalogue\EmojiCollection;

/**
 * @method static Emoji get(Emoji|EmojiId|string $key)
 * @method static Emoji|null find(Emoji|EmojiId|string $key)
 * @method static bool has(string $key)
 * @method static Emoji flag(string $region)
 * @method static EmojiCollection all()
 * @method static Query query()
 * @method static EmojiCollection search(string $term, ?string $locale = null, int $limit = 24)
 * @method static Emoji random(?Group $group = null)
 * @method static list<Kaomoji> kaomoji(?string $group = null, bool $asciiOnly = false)
 * @method static TextConverter text(string $text)
 * @method static TextConverter html(string $html)
 * @method static string convert(string $text, Mode $to, Mode ...$from)
 * @method static string strip(string $text)
 * @method static bool contains(string $text)
 * @method static int count(string $text)
 * @method static bool isOnlyEmoji(string $text)
 * @method static \Simtabi\Laranail\Emojis\Core\Emojis addCustom(string $name, EmojiImage|string $image, ?string $fallback = null, list<string> $aliases = [], ?string $label = null)
 * @method static \Simtabi\Laranail\Emojis\Core\Emojis useImage(Emoji|EmojiId|string $emoji, EmojiImage|string $image)
 * @method static EmojiImage image(EmojiImage|string $image)
 * @method static Symbols symbols()
 * @method static \Simtabi\Laranail\Emojis\Core\Emojis addShortcode(string $shortcode, Emoji|EmojiId|string $emoji)
 * @method static \Simtabi\Laranail\Emojis\Core\Emojis addEmoticon(string $emoticon, Emoji|EmojiId|string $emoji)
 * @method static \Simtabi\Laranail\Emojis\Core\Emojis addImageSet(ImageSet $set)
 * @method static string datasetVersion()
 *
 * @see EmojisService
 */
final class Emojis extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EmojisService::class;
    }
}
