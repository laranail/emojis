<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Extension;

use InvalidArgumentException;
use Simtabi\Laranail\Emojis\Core\Emojis;

/**
 * Applies the registration half of a config array — `extend.custom`, `extend.images`, `extend.shortcodes`,
 * `extend.emoticons` and `input.disabled_emoticons` — to an instance.
 *
 * The one implementation both entry points share: the Laravel provider passes its `laranail.emojis` config
 * and Emojis::create() passes the array it was given, so plain PHP and Laravel read the same file the same
 * way. Until 0.4 only the provider read these keys and create() ignored them without a word.
 */
final class ConfiguredExtensions
{
    /**
     * @param array<array-key, mixed> $config the shape of config/emojis.php
     *
     * @throws InvalidArgumentException on a 0.1.0 `url` key, or anything the add*() methods refuse
     */
    public static function apply(Emojis $emojis, array $config): Emojis
    {
        $extend = (array) ($config['extend'] ?? []);

        foreach ((array) ($extend['custom'] ?? []) as $name => $custom) {
            $custom = (array) $custom;

            if (array_key_exists('url', $custom)) {
                throw new InvalidArgumentException("laranail.emojis.extend.custom.{$name}.url was renamed to image in 0.2.0; it now also accepts a data URI.");
            }

            $emojis->addCustom(
                (string) $name,
                self::string($custom['image'] ?? ''),
                isset($custom['fallback']) ? self::string($custom['fallback']) : null,
                array_values(array_map(self::string(...), (array) ($custom['aliases'] ?? []))),
                isset($custom['label']) ? self::string($custom['label']) : null,
            );
        }

        foreach ((array) ($extend['images'] ?? []) as $emoji => $image) {
            $emojis->useImage((string) $emoji, self::string($image));
        }

        foreach ((array) ($extend['shortcodes'] ?? []) as $code => $emoji) {
            $emojis->addShortcode((string) $code, self::string($emoji));
        }

        foreach ((array) ($extend['emoticons'] ?? []) as $emoticon => $emoji) {
            $emojis->addEmoticon((string) $emoticon, self::string($emoji));
        }

        $disabled = (array) (((array) ($config['input'] ?? []))['disabled_emoticons'] ?? []);

        return $emojis->removeEmoticon(...array_map(self::string(...), array_values($disabled)));
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
