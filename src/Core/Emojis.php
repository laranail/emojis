<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core;

use Closure;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Data\Record;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Text\Scanner;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiId;
use Simtabi\Laranail\Emojis\Core\Locale\Locales;
use Simtabi\Laranail\Emojis\Core\Catalogue\Query;
use Simtabi\Laranail\Emojis\Core\Render\Renderer;
use Simtabi\Laranail\Emojis\Core\Symbols\Symbols;
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;
use Simtabi\Laranail\Emojis\Core\Render\ImageSets;
use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;
use Simtabi\Laranail\Emojis\Core\Support\Macroable;
use Simtabi\Laranail\Emojis\Core\Contracts\ImageSet;
use Simtabi\Laranail\Emojis\Core\Security\Sanitizer;
use Simtabi\Laranail\Emojis\Core\Text\TextConverter;
use Simtabi\Laranail\Emojis\Core\Catalogue\Catalogue;
use Simtabi\Laranail\Emojis\Core\Contracts\HtmlFactory;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmoji;
use Simtabi\Laranail\Emojis\Core\Contracts\EmojisFluent;
use Simtabi\Laranail\Emojis\Core\Contracts\TerminalProbe;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidImage;
use Simtabi\Laranail\Emojis\Core\Exceptions\EmojiNotFound;
use Simtabi\Laranail\Emojis\Core\Catalogue\EmojiCollection;
use Simtabi\Laranail\Emojis\Core\Contracts\FailureReporter;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;
use Simtabi\Laranail\Emojis\Core\Support\DefaultHtmlFactory;
use Simtabi\Laranail\Emojis\Core\Exceptions\DatasetException;
use Simtabi\Laranail\Emojis\Core\Support\Psr3FailureReporter;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidCustomEmoji;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmojiRegistry;

/**
 * The entry point: the whole catalogue, every conversion, and the extension seams.
 *
 * Framework-free. Outside Laravel:
 *
 *     $emojis = Emojis::create(['locale' => ['default' => 'fr'], 'images' => ['set' => 'noto']]);
 *     $emojis->text('Bonjour :wave:')->toEmoji();            // "Bonjour 👋"
 *
 * Inside Laravel the container builds one per request scope from config/emojis.php, and the facade,
 * the `emoji()` helper and the Blade component all resolve it.
 *
 * Everything is instance-scoped — custom emoji, image sets, caches — so two instances never share state.
 * Macros (Macroable) are the one process-global seam, as in Laravel.
 */
final class Emojis implements EmojisFluent
{
    use Macroable;

    private ?Catalogue $catalogue = null;

    private ?Scanner $scanner = null;

    private ?Renderer $renderer = null;

    private ?Locales $locales = null;

    private ?Symbols $symbols = null;

    /** @var array<string, string>|null region => hexcode */
    private ?array $flags = null;

    /** @param (Closure(): ?string)|null $currentLocale */
    public function __construct(private readonly DatasetStore $data, private readonly Options $options = new Options, private readonly FailureReporter $reporter = new Psr3FailureReporter, private readonly TerminalProbe $terminal = new EnvTerminalProbe, private readonly HtmlFactory $html = new DefaultHtmlFactory, private readonly CustomEmojiRegistry $custom = new CustomEmojiRegistry, private readonly ?Closure $currentLocale = null, private ?ImageSets $images = null) {}

    /**
     * Build an instance for plain PHP.
     *
     * @param array<string, mixed>|Options $config the shape of config/emojis.php, or an Options
     */
    public static function create(
        array|Options $config = [],
        ?FailureReporter $reporter = null,
        ?TerminalProbe $terminal = null,
        ?DatasetStore $data = null,
    ): self {
        return new self(
            data: $data ?? DatasetStore::packaged(),
            options: $config instanceof Options ? $config : Options::fromArray($config),
            reporter: $reporter ?? new Psr3FailureReporter,
            terminal: $terminal ?? new EnvTerminalProbe,
        );
    }

    /** Absolute path of a built asset under the package's public/assets. */
    public static function assetPath(string $asset = ''): string
    {
        return dirname(__DIR__, 2) . '/public/assets' . ($asset === '' ? '' : '/' . ltrim($asset, '/'));
    }

    // ---- lookups --------------------------------------------------------------------------------

    /** @throws EmojiNotFound */
    public function get(Emoji|EmojiId|string $key): Emoji
    {
        return $this->find($key) ?? throw EmojiNotFound::for('key', $key instanceof EmojiId ? $key->value : (string) $key);
    }

    /** By EmojiId, character (any qualification), hexcode, shortcode, slug or emoticon. */
    public function find(Emoji|EmojiId|string $key): ?Emoji
    {
        return $this->catalogue()->find($key);
    }

    public function has(string $key): bool
    {
        return $this->find($key) instanceof Emoji;
    }

    public function fromHexcode(string $hexcode): ?Emoji
    {
        return $this->catalogue()->byHexcode($hexcode);
    }

    public function fromChar(string $char): ?Emoji
    {
        return $this->catalogue()->byChar($char);
    }

    public function fromShortcode(string $shortcode): ?Emoji
    {
        return $this->catalogue()->byShortcode($shortcode);
    }

    public function fromEmoticon(string $emoticon): ?Emoji
    {
        return $this->catalogue()->byEmoticon($emoticon);
    }

    /**
     * Every emoticon text conversion recognises, mapped to its emoji: the dataset's and any added with
     * addEmoticon(). Opt-in-only ("risky") ones are included only when asked, as withEmoticons() does.
     *
     * @return array<string, Emoji> emoticon => emoji
     */
    public function emoticons(bool $risky = false): array
    {
        $excluded = $risky ? [] : array_flip($this->data->riskyEmoticons());
        $out = [];

        foreach (array_keys([...$this->data->emoticonMap(), ...$this->custom->emoticons()]) as $emoticon) {
            $emoticon = (string) $emoticon;

            if (! isset($excluded[$emoticon]) && ($emoji = $this->catalogue()->byEmoticon($emoticon)) instanceof Emoji) {
                $out[$emoticon] = $emoji;
            }
        }

        return $out;
    }

    /**
     * A flag by ISO 3166-1 alpha-2 region ("KE", "gb"), or one of the three RGI subdivision flags
     * ("GB-ENG", "gbsct"). Only RGI flags exist: "XX" throws, and "UK" is not silently read as "GB".
     *
     * @throws EmojiNotFound
     */
    public function flag(string $region): Emoji
    {
        if ($this->flags === null) {
            $this->flags = [];

            foreach ($this->data->emojis() as $hex => $record) {
                if ($record[Record::REGION] !== null) {
                    $this->flags[$record[Record::REGION]] = (string) $hex;
                }
            }
        }

        $key = strtoupper(str_replace(['-', '_', ' '], '', $region));
        $hex = $this->flags[$key] ?? throw EmojiNotFound::for('flag', $region);

        return $this->catalogue()->byHexcode($hex) ?? throw EmojiNotFound::for('flag', $region);
    }

    /** Every emoji and component, in CLDR order. query() for filtering. */
    public function all(): EmojiCollection
    {
        return new EmojiCollection($this->catalogue()->all());
    }

    public function query(): Query
    {
        return new Query($this->catalogue(), $this->locales());
    }

    public function search(string $term, ?string $locale = null, int $limit = 24): EmojiCollection
    {
        return $this->query()->search($term, $locale)->limit($limit)->get();
    }

    public function random(?Group $group = null): Emoji
    {
        $pool = $this->query()->group(...($group instanceof Group ? [$group] : []))->get()->all();

        return $pool[random_int(0, count($pool) - 1)];
    }

    /**
     * A curated collection by name — `japanese`: the Japanese-text buttons (🈁 … 🉑), and Japanese-origin
     * symbols, places, culture and food. `collections()` lists the names.
     *
     * @throws EmojiNotFound for an unknown collection
     */
    public function collection(string $name): EmojiCollection
    {
        $hexcodes = $this->data->collections()[$name] ?? throw EmojiNotFound::for('collection', $name);
        $catalogue = $this->catalogue();

        return new EmojiCollection(array_values(array_filter(array_map($catalogue->byHexcode(...), $hexcodes))));
    }

    /** @return list<string> */
    public function collections(): array
    {
        return array_keys($this->data->collections());
    }

    /**
     * Kaomoji and text faces, optionally one group ("shrugging", "table_flipping").
     *
     * @return list<Kaomoji>
     */
    public function kaomoji(?string $group = null, bool $asciiOnly = false): array
    {
        $out = [];

        foreach ($this->data->kaomojiItems() as $item) {
            if (($group === null || $item['group'] === $group) && (! $asciiOnly || $item['ascii'])) {
                $out[] = $this->kaomojiFrom($item);
            }
        }

        return $out;
    }

    /**
     * Kaomoji whose description, tags or kana readings contain the term: `searchKaomoji('ねこ')`,
     * `searchKaomoji('shrug')`.
     *
     * @return list<Kaomoji>
     */
    public function searchKaomoji(string $term, int $limit = 50): array
    {
        $term = mb_strtolower(trim($term), 'UTF-8');
        $out = [];

        foreach ($term === '' ? [] : $this->data->kaomojiItems() as $item) {
            $haystack = mb_strtolower($item['description'] . ' | ' . $item['tags'] . ' | ' . $item['reading'] . ' | ' . $item['group'], 'UTF-8');

            if (str_contains($haystack, $term)) {
                $out[] = $this->kaomojiFrom($item);

                if (count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }

    /** @return array<string, string> slug => label */
    public function kaomojiGroups(): array
    {
        return $this->data->kaomojiGroups();
    }

    // ---- conversion -----------------------------------------------------------------------------

    /** Convert plain text. See TextConverter. */
    public function text(string $text): TextConverter
    {
        return new TextConverter($this, $text);
    }

    /** Convert HTML: text runs only, markup untouched, code and pre left alone. */
    public function html(string $html): TextConverter
    {
        return new TextConverter($this, $html, isHtml: true);
    }

    /** One-shot conversion: convert('Hi :)', Mode::Emoji, Mode::Emoticon, Mode::Shortcode). */
    public function convert(string $text, Mode $to, Mode ...$from): string
    {
        $converter = $this->text($text);

        return ($from === [] ? $converter : $converter->from(...$from))->to($to);
    }

    /**
     * Make untrusted text safe: remove smuggled and invisible characters, then apply the configured emoji
     * policy (permissive unless configured). See Security\Sanitizer.
     */
    public function sanitize(string $text): Sanitizer
    {
        return new Sanitizer($this, $text, $this->options->policy);
    }

    /**
     * The stylesheet for emoji in web UI: sizing for fitted images and a tight box for native emoji. This is
     * the built public/assets/css/emojis.css (source: resources/assets/styles/emojis.scss). Inline it with a
     * nonce under a strict CSP, or publish it and link it.
     *
     * @throws DatasetException when the built file is missing
     */
    public function stylesheet(): string
    {
        $path = self::assetPath('css/emojis.css');
        $css = is_file($path) ? file_get_contents($path) : false;

        if ($css === false || $css === '') {
            throw DatasetException::assetMissing('css/emojis.css', $path);
        }

        return $css;
    }

    public function strip(string $text): string
    {
        return $this->text($text)->from(Mode::Unicode)->strip();
    }

    public function contains(string $text): bool
    {
        return $this->text($text)->from(Mode::Unicode)->contains();
    }

    public function count(string $text): int
    {
        return $this->text($text)->from(Mode::Unicode)->count(true);
    }

    public function isOnlyEmoji(string $text): bool
    {
        return $this->text($text)->from(Mode::Unicode)->isOnlyEmoji();
    }

    /** What Mode::Auto means here: Emoji when the terminal can show it, the configured fallback otherwise. */
    public function resolveAuto(): Mode
    {
        $fallback = $this->options->autoFallback === Mode::Auto ? Mode::Ascii : $this->options->autoFallback;

        return $this->terminal->supportsEmoji() ? Mode::Emoji : $fallback;
    }

    // ---- extension ------------------------------------------------------------------------------

    /**
     * Register an image-only custom emoji (":laravel:"). Its name must not shadow a Unicode shortcode or
     * another custom emoji. The image is an https or root-relative URL, a data URI, or an EmojiImage built
     * from base64, bytes or a file; it is validated against the configured image policy.
     *
     * @param list<string> $aliases
     *
     * @throws InvalidCustomEmoji
     * @throws InvalidImage
     */
    public function addCustom(string $name, EmojiImage|string $image, ?string $fallback = null, array $aliases = [], ?string $label = null): self
    {
        $custom = new CustomEmoji($name, $image, $fallback, $aliases, $label, $this->options->imagePolicy);

        foreach ([$custom->name, ...$custom->aliases] as $code) {
            if ($this->catalogue()->byShortcode($code) instanceof Emoji) {
                throw InvalidCustomEmoji::collides($code);
            }
        }

        $this->custom->add($custom);

        return $this;
    }

    /**
     * Use your own image for an existing emoji in Image mode, ahead of any image set — a brand's thumbs-up,
     * a PNG for a platform the sets do not draw. Same validation as custom emoji.
     *
     * @throws InvalidImage
     */
    public function useImage(Emoji|EmojiId|string $emoji, EmojiImage|string $image): self
    {
        $this->custom->image($this->get($emoji)->hexcode, EmojiImage::from($image, $this->options->imagePolicy));

        return $this;
    }

    /**
     * Validate an image against the configured policy without registering it: for form input, uploads and
     * anything else that will end up in an <img src>.
     *
     * @throws InvalidImage
     */
    public function image(EmojiImage|string $image): EmojiImage
    {
        return EmojiImage::from($image, $this->options->imagePolicy);
    }

    /** The replacement image registered for an emoji with useImage(), if any. */
    public function customImage(Emoji $emoji): ?EmojiImage
    {
        return $this->custom->imageFor($emoji->hexcode);
    }

    /** An extra shortcode for an existing emoji. */
    public function addShortcode(string $shortcode, Emoji|EmojiId|string $emoji): self
    {
        $this->custom->shortcode($shortcode, $this->get($emoji)->hexcode);

        return $this;
    }

    /** An extra ASCII emoticon for an existing emoji. */
    public function addEmoticon(string $emoticon, Emoji|EmojiId|string $emoji): self
    {
        $this->custom->emoticon($emoticon, $this->get($emoji)->hexcode);
        $this->scanner = null; // the emoticon pattern is built from the registry

        return $this;
    }

    public function addImageSet(ImageSet $set): self
    {
        $this->images()->register($set);

        return $this;
    }

    /**
     * Make custom emoji, shortcodes, emoticons and image sets read-only. The Laravel provider calls this once
     * the application has booted, which is what makes a worker-wide singleton safe under Octane: nothing a
     * request does can change what the next request sees.
     */
    public function freeze(): self
    {
        $this->custom->freeze();
        $this->images()->freeze();

        return $this;
    }

    public function customEmoji(string $name): ?CustomEmoji
    {
        return $this->custom->find($name);
    }

    // ---- collaborators ------------------------------------------------------------------------

    public function options(): Options
    {
        return $this->options;
    }

    public function datasetVersion(): string
    {
        return $this->data->version();
    }

    /** @return list<string> */
    public function availableLocales(): array
    {
        return $this->data->availableLocales();
    }

    /** Special characters that are not emoji: arrows, currency, maths, letters, punctuation, hieroglyphs… */
    public function symbols(): Symbols
    {
        return $this->symbols ??= new Symbols($this->data);
    }

    public function images(): ImageSets
    {
        return $this->images ??= new ImageSets($this->data, $this->options->imageBaseUrls);
    }

    public function locales(): Locales
    {
        return $this->locales ??= new Locales($this->data, $this->reporter, $this->options, $this->currentLocale);
    }

    public function reporter(): FailureReporter
    {
        return $this->reporter;
    }

    public function terminal(): TerminalProbe
    {
        return $this->terminal;
    }

    public function htmlFactory(): HtmlFactory
    {
        return $this->html;
    }

    /** @internal */
    public function dataset(): DatasetStore
    {
        return $this->data;
    }

    /** @internal */
    public function catalogue(): Catalogue
    {
        return $this->catalogue ??= new Catalogue($this, $this->data, $this->custom);
    }

    /** @internal */
    public function scanner(): Scanner
    {
        return $this->scanner ??= new Scanner($this->data, $this->catalogue(), $this->custom);
    }

    /** @internal */
    public function renderer(): Renderer
    {
        return $this->renderer ??= new Renderer($this);
    }

    /** @param array{value: string, group: string, description: string, ascii: bool, tags: string, reading: string} $item */
    private function kaomojiFrom(array $item): Kaomoji
    {
        $split = static fn (string $joined): array => $joined === '' ? [] : explode(' | ', $joined);

        return new Kaomoji($item['value'], $item['group'], $item['description'], $item['ascii'], $split($item['tags']), $split($item['reading']));
    }
}
