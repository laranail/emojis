<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core;

use InvalidArgumentException;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\ShortcodePreset;

/**
 * Defaults for every conversion, built from a plain array so the Laravel config file stays
 * var_export-serializable (strings, ints, bools and lists only — no enum instances, no closures).
 *
 * Every value here is a default a converter call can override fluently.
 */
final readonly class Options
{
    /**
     * @param array<string, list<Mode>> $degradation target mode value => ordered fallback modes
     * @param array<string, string> $imageBaseUrls image set name => base URL override (self-hosting)
     */
    public function __construct(
        public string $locale = 'en',
        public string $fallbackLocale = 'en',
        public ShortcodePreset $preset = ShortcodePreset::GitHub,
        public string $imageSet = 'twemoji',
        public array $imageBaseUrls = [],
        public string $imageClass = 'emoji',
        public string $nameTemplate = '[{name}]',
        public string $shortcodeOpen = ':',
        public string $shortcodeClose = ':',
        public array $degradation = [],
        public Mode $autoFallback = Mode::Ascii,
        public int $maxInputBytes = 1_048_576,
    ) {
        if ($maxInputBytes < 1) {
            throw new InvalidArgumentException('maxInputBytes must be positive.');
        }

        if (! str_contains($nameTemplate, '{name}')) {
            throw new InvalidArgumentException('nameTemplate must contain {name}.');
        }
    }

    /**
     * Build from a config array (the shape of config/emojis.php). Unknown keys are ignored so a published
     * config from an older version keeps working; wrong types throw, because a typo there is a bug.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $string = static fn (string $key, string $default): string => is_string($config[$key] ?? null) && $config[$key] !== '' ? $config[$key] : $default;

        $degradation = [];

        foreach ((array) ($config['degradation'] ?? []) as $target => $chain) {
            $target = Mode::from((string) $target)->value;
            $degradation[$target] = array_values(array_map(static fn (mixed $mode): Mode => $mode instanceof Mode ? $mode : Mode::from(is_string($mode) ? $mode : ''), (array) $chain));
        }

        $delimiters = (array) ($config['shortcode_delimiters'] ?? [':', ':']);
        $baseUrls = [];

        foreach ((array) ($config['image_base_urls'] ?? []) as $set => $url) {
            if (is_string($url) && $url !== '') {
                $baseUrls[(string) $set] = $url;
            }
        }

        return new self(
            locale: $string('locale', 'en'),
            fallbackLocale: $string('fallback_locale', 'en'),
            preset: ShortcodePreset::from($string('shortcode_preset', ShortcodePreset::GitHub->value)),
            imageSet: $string('image_set', 'twemoji'),
            imageBaseUrls: $baseUrls,
            imageClass: $string('image_class', 'emoji'),
            nameTemplate: $string('name_template', '[{name}]'),
            shortcodeOpen: is_string($delimiters[0] ?? null) ? $delimiters[0] : ':',
            shortcodeClose: is_string($delimiters[1] ?? null) ? $delimiters[1] : ':',
            degradation: $degradation,
            autoFallback: Mode::from($string('auto_fallback', Mode::Ascii->value)),
            maxInputBytes: is_int($config['max_input_bytes'] ?? null) ? $config['max_input_bytes'] : 1_048_576,
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
            Mode::Emoji    => [Mode::Shortcode],
            default        => [],
        };
    }
}
