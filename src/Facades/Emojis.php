<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Kaomoji;
use Simtabi\Laranail\Emojis\Core\Options;
use Simtabi\Laranail\Emojis\Core\Tags\Tags;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiId;
use Simtabi\Laranail\Emojis\Core\Locale\Locales;
use Simtabi\Laranail\Emojis\Core\Catalogue\Query;
use Simtabi\Laranail\Emojis\Core\Symbols\Symbols;
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;
use Simtabi\Laranail\Emojis\Core\Render\ImageSets;
use Simtabi\Laranail\Emojis\Core\Contracts\ImageSet;
use Simtabi\Laranail\Emojis\Core\Security\Sanitizer;
use Simtabi\Laranail\Emojis\Core\Text\TextConverter;
use Simtabi\Laranail\Emojis\Core\Contracts\HtmlFactory;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmoji;
use Simtabi\Laranail\Emojis\Core\Contracts\TerminalProbe;
use Simtabi\Laranail\Emojis\Core\Emojis as EmojisService;
use Simtabi\Laranail\Emojis\Core\Catalogue\EmojiCollection;
use Simtabi\Laranail\Emojis\Core\Contracts\FailureReporter;

/**
 * Every public Emojis method, in declaration order; tests/Unit/FacadeTest.php fails when one is missing or
 * its parameters drift.
 *
 * @method static Emoji get(Emoji|EmojiId|string $key)
 * @method static Emoji|null find(Emoji|EmojiId|string $key)
 * @method static bool has(Emoji|EmojiId|string $key)
 * @method static Emoji|null fromHexcode(string $hexcode)
 * @method static Emoji|null fromChar(string $char)
 * @method static Emoji|null fromShortcode(string $shortcode)
 * @method static Emoji|null fromEmoticon(string $emoticon)
 * @method static array<string, Emoji> emoticons(bool $risky = false)
 * @method static Emoji flag(string $region)
 * @method static EmojiCollection all()
 * @method static Query query()
 * @method static EmojiCollection search(string $term, ?string $locale = null, int $limit = 24)
 * @method static Emoji random(?Group $group = null)
 * @method static EmojiCollection collection(string $name)
 * @method static list<string> collections()
 * @method static list<Kaomoji> kaomoji(?string $group = null, bool $asciiOnly = false)
 * @method static list<Kaomoji> searchKaomoji(string $term, int $limit = 50)
 * @method static array<string, string> kaomojiGroups()
 * @method static TextConverter text(string $text)
 * @method static TextConverter html(string $html)
 * @method static string convert(string $text, Mode $to, Mode ...$from)
 * @method static Sanitizer sanitize(string $text)
 * @method static string stylesheet()
 * @method static string strip(string $text)
 * @method static bool contains(string $text)
 * @method static int count(string $text)
 * @method static bool isOnlyEmoji(string $text)
 * @method static Mode resolveAuto()
 * @method static EmojisService addCustom(string $name, EmojiImage|string $image, ?string $fallback = null, list<string> $aliases = [], ?string $label = null)
 * @method static EmojisService useImage(Emoji|EmojiId|string $emoji, EmojiImage|string $image)
 * @method static EmojiImage image(EmojiImage|string $image)
 * @method static EmojiImage|null customImage(Emoji $emoji)
 * @method static EmojisService addShortcode(string $shortcode, Emoji|EmojiId|string $emoji)
 * @method static EmojisService addEmoticon(string $emoticon, Emoji|EmojiId|string $emoji)
 * @method static EmojisService removeEmoticon(string ...$emoticons)
 * @method static EmojisService addImageSet(ImageSet $set)
 * @method static EmojisService freeze()
 * @method static CustomEmoji|null customEmoji(string $name)
 * @method static list<CustomEmoji> customEmojis()
 * @method static Options options()
 * @method static string datasetVersion()
 * @method static list<string> availableLocales()
 * @method static Symbols symbols()
 * @method static Tags tags()
 * @method static ImageSets images()
 * @method static Locales locales()
 * @method static FailureReporter reporter()
 * @method static TerminalProbe terminal()
 * @method static HtmlFactory htmlFactory()
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
