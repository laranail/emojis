<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core;

use InvalidArgumentException;
use Simtabi\Laranail\Emojis\Core\Enums\Fit;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\ImageSource;
use Simtabi\Laranail\Emojis\Core\Image\ImagePolicy;
use Simtabi\Laranail\Emojis\Core\Security\EmojiPolicy;
use Simtabi\Laranail\Emojis\Core\Enums\ShortcodePreset;

/**
 * Defaults for every conversion, built from a plain array so the Laravel config file stays
 * var_export-serializable (strings, ints, bools and lists only — no enum instances, no closures).
 *
 * Every value here is a default a converter call can override fluently.
 */
final readonly class Options
{
    /** The 0.1.0 flat keys and where each moved, so an old published config fails with directions. */
    private const array LEGACY_KEYS = [
        'fallback_locale'      => 'locale.fallback',
        'shortcode_preset'     => 'shortcodes.preset',
        'shortcode_delimiters' => 'shortcodes.delimiters',
        'image_set'            => 'images.set',
        'image_base_urls'      => 'images.base_urls',
        'image_class'          => 'images.class',
        'image_fit'            => 'images.fit',
        'name_template'        => 'output.name_template',
        'auto_fallback'        => 'output.auto_fallback',
        'degradation'          => 'output.degradation',
        'max_input_bytes'      => 'input.max_bytes',
        'custom'               => 'extend.custom',
        'emoticons'            => 'extend.emoticons',
    ];

    /**
     * @param array<string, list<Mode>> $degradation target mode value => ordered fallback modes
     * @param array<string, string> $imageBaseUrls image set name => base URL override (self-hosting)
     * @param string $imageClass extra classes for rendered images, after the package's own `laranail-emoji laranail-emoji-image`
     */
    public function __construct(
        public string $locale = 'en',
        public string $fallbackLocale = 'en',
        public ShortcodePreset $preset = ShortcodePreset::GitHub,
        public string $imageSet = 'twemoji',
        public array $imageBaseUrls = [],
        public string $imageClass = '',
        public string $nameTemplate = '[{name}]',
        public string $shortcodeOpen = ':',
        public string $shortcodeClose = ':',
        public array $degradation = [],
        public Mode $autoFallback = Mode::Ascii,
        public int $maxInputBytes = 1_048_576,
        public EmojiPolicy $policy = new EmojiPolicy,
        public Fit $imageFit = Fit::Balanced,
        public ImagePolicy $imagePolicy = new ImagePolicy,
        public ImageSource $imageSource = ImageSource::Cdn,
    ) {
        if ($maxInputBytes < 1) {
            throw new InvalidArgumentException('maxInputBytes must be positive.');
        }

        if (! str_contains($nameTemplate, '{name}')) {
            throw new InvalidArgumentException('nameTemplate must contain {name}.');
        }
    }

    /**
     * Build from a config array — the shape of config/emojis.php: `locale`, `shortcodes`, `images`, `output`,
     * `input` and `policy` groups (the `extend` group is registration, not options, and is read by the
     * Laravel provider). Unknown keys are ignored so a newer published config keeps working on an older
     * package; wrong types throw, because a typo there is a bug.
     *
     * The flat 0.1.0 layout (`image_set`, `shortcode_preset`, …) is refused rather than silently read as
     * defaults: a published config that no longer applies should stop boot, not quietly change output.
     *
     * @param array<string, mixed> $config
     *
     * @throws InvalidArgumentException naming each 0.1.0 key found and where it moved
     */
    public static function fromArray(array $config): self
    {
        $legacy = array_intersect_key(self::LEGACY_KEYS, $config);

        // Two 0.1.0 keys kept their names but changed shape: `locale` was a string, and `shortcodes` held extra
        // codes (now `extend.shortcodes`) rather than the preset and delimiters.
        if (is_string($config['locale'] ?? null)) {
            $legacy['locale'] = 'locale.default';
        }

        if (is_array($config['shortcodes'] ?? null) && array_diff(array_keys($config['shortcodes']), ['preset', 'delimiters']) !== []) {
            $legacy['shortcodes'] = 'extend.shortcodes';
        }

        if ($legacy !== []) {
            throw new InvalidArgumentException('laranail/emojis config uses the 0.1.0 layout; republish it (vendor:publish --tag=laranail::emojis-config --force) or move: '
                . implode(', ', array_map(static fn (string $old, string $new): string => "{$old} → {$new}", array_keys($legacy), $legacy)) . '.');
        }

        $group = static fn (string $name): array => self::stringKeyed($config[$name] ?? null);
        $locale = $group('locale');
        $shortcodes = $group('shortcodes');
        $images = $group('images');
        $output = $group('output');
        $input = $group('input');
        $string = static fn (array $in, string $key, string $default): string => is_string($in[$key] ?? null) && $in[$key] !== '' ? $in[$key] : $default;

        $degradation = [];

        foreach (self::stringKeyed($output['degradation'] ?? null) as $target => $chain) {
            $degradation[Mode::from($target)->value] = array_values(array_map(
                static fn (mixed $mode): Mode => $mode instanceof Mode ? $mode : Mode::from(is_string($mode) ? $mode : ''),
                is_array($chain) ? $chain : [$chain],
            ));
        }

        $baseUrls = array_filter(
            array_map(static fn (mixed $url): string => is_string($url) ? $url : '', self::stringKeyed($images['base_urls'] ?? null)),
            static fn (string $url): bool => $url !== '',
        );
        $delimiters = is_array($shortcodes['delimiters'] ?? null) ? array_values($shortcodes['delimiters']) : [];

        return new self(
            locale: $string($locale, 'default', 'en'),
            fallbackLocale: $string($locale, 'fallback', 'en'),
            preset: ShortcodePreset::from($string($shortcodes, 'preset', ShortcodePreset::GitHub->value)),
            imageSet: $string($images, 'set', 'twemoji'),
            imageBaseUrls: $baseUrls,
            imageClass: is_string($images['class'] ?? null) ? trim($images['class']) : '',
            nameTemplate: $string($output, 'name_template', '[{name}]'),
            shortcodeOpen: is_string($delimiters[0] ?? null) ? $delimiters[0] : ':',
            shortcodeClose: is_string($delimiters[1] ?? null) ? $delimiters[1] : ':',
            degradation: $degradation,
            autoFallback: Mode::from($string($output, 'auto_fallback', Mode::Ascii->value)),
            maxInputBytes: is_int($input['max_bytes'] ?? null) ? $input['max_bytes'] : 1_048_576,
            policy: EmojiPolicy::fromArray($group('policy')),
            imageFit: Fit::from($string($images, 'fit', Fit::Balanced->value)),
            imagePolicy: ImagePolicy::fromArray(self::stringKeyed($images['custom'] ?? null)),
            imageSource: ImageSource::from($string($images, 'source', ImageSource::Cdn->value)),
        );
    }

    /**
     * The fallback chain for a target, defaults first overridden by config. Every chain ends in a mode
     * that can always render (Unicode or Shortcode), so a non-strict conversion never fails.
     *
     * @return list<Mode>
     */
    public function degradationFor(Mode $target): array
    {
        return $this->degradation[$target->value] ?? match ($target) {
            Mode::Text     => [Mode::Unicode],
            Mode::Emoticon => [Mode::Shortcode],
            Mode::Image    => [Mode::Unicode],
            Mode::Carrier  => [Mode::Unicode],
            Mode::Emoji    => [Mode::Shortcode],
            default        => [],
        };
    }

    /** @return array<string, mixed> */
    private static function stringKeyed(mixed $value): array
    {
        $out = [];

        foreach (is_array($value) ? $value : [] as $key => $item) {
            $out[(string) $key] = $item;
        }

        return $out;
    }
}
