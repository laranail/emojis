<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Picker;

use Throwable;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Contracts\ImageSet;
use Simtabi\Laranail\Emojis\Core\Render\ImageSets\CdnImageSet;

/**
 * What a picker needs to draw emoji from an image set: for a set whose filenames follow a rule a browser can
 * apply (Twemoji, Noto, OpenMoji, JoyPixels), the base URL, the rule, the suffix and the few hexcodes the set
 * does not cover — a kilobyte or two, however many emoji there are. For any other set (Fluent, a custom
 * ImageSet), the path (below one base URL) or URL of every emoji it covers, which only the server can work
 * out — far larger, so a set like Fluent is worth choosing only with the API delivering the payload.
 *
 * @phpstan-type PickerImageSet array{set: string, licence: string, base?: string, rule?: string, suffix?: string, missing?: list<string>, paths?: array<string, string>, urls?: array<string, string>}
 */
final readonly class PickerImages
{
    public function __construct(private Emojis $emojis) {}

    /**
     * @param list<string> $hexcodes every hexcode the payload can show (each emoji and each toned form)
     *
     * @return PickerImageSet|null null for a set that does not exist
     */
    public function describe(string $name, array $hexcodes): ?array
    {
        try {
            $set = $this->emojis->images()->get($name);
        } catch (Throwable) {
            return null;
        }

        $emoji = array_filter(array_map($this->emojis->fromHexcode(...), $hexcodes));

        if ($set instanceof CdnImageSet && $set->rule() !== null) {
            return [
                'set'     => $set->name(),
                'licence' => $set->licence(),
                'base'    => $set->baseUrl(),
                'rule'    => $set->rule(),
                'suffix'  => $set->suffix(),
                'missing' => array_values(array_map(static fn (Emoji $e): string => $e->hexcode, array_filter($emoji, static fn (Emoji $e): bool => ! $set->covers($e)))),
            ];
        }

        // A set on one base URL (Fluent) sends each path below it, not the whole URL again for every emoji.
        if ($set instanceof CdnImageSet) {
            $paths = [];

            foreach ($emoji as $e) {
                $path = $set->path($e);

                if ($path !== null) {
                    $paths[$e->hexcode] = $path;
                }
            }

            return ['set' => $set->name(), 'licence' => $set->licence(), 'base' => $set->baseUrl(), 'paths' => $paths];
        }

        return ['set' => $set->name(), 'licence' => $set->licence(), 'urls' => $this->urls($set, $emoji)];
    }

    /**
     * @param array<array-key, Emoji> $emoji
     *
     * @return array<string, string>
     */
    private function urls(ImageSet $set, array $emoji): array
    {
        $urls = [];

        foreach ($emoji as $e) {
            $url = $set->url($e);

            if (is_string($url) && $url !== '') {
                $urls[$e->hexcode] = $url;
            }
        }

        return $urls;
    }
}
