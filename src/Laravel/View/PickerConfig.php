<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View;

use Illuminate\Contracts\Config\Repository;

/**
 * The picker's defaults from `laranail.emojis.picker`, read once and checked: anything missing or of the
 * wrong type takes the built-in default, so a typo in config degrades to the documented behaviour instead of
 * reaching the browser. Component attributes override these; data-laranail-emoji-* attributes override both.
 */
final readonly class PickerConfig
{
    public const array FEATURES = ['search', 'recents', 'skin_tones', 'per_person_tones', 'preview', 'category_tabs', 'custom', 'kaomoji', 'symbols', 'set_switcher'];

    /** Off unless switched on: each adds to the payload. */
    public const array OPT_IN = ['kaomoji', 'symbols', 'set_switcher'];

    /** The image sets a user may switch between: the built-in ones whose URLs a browser can work out. */
    public const array SWITCHABLE = ['twemoji', 'noto', 'openmoji'];

    public const array PLACEMENTS = ['auto', 'top', 'bottom', 'start', 'end', 'top-start', 'top-end', 'bottom-start', 'bottom-end', 'start-start', 'start-end', 'end-start', 'end-end'];

    /** @param array<string, bool> $features */
    public function __construct(
        public array $features,
        public string $placement,
        public int $offset,
        public bool $arrow,
        public int $sheetBreakpoint,
        public int $columns,
        public int $maxRecent,
        public string $sort,
        public string $recentOrder,
        public bool $closeOnSelect,
        public string $trigger,
        public string $theme,
        public string $delivery,
        public string $render = 'auto',
        public ?string $imageSet = null,
    ) {}

    public static function fromConfig(Repository $config): self
    {
        $picker = $config->get('laranail.emojis.picker');

        return self::fromArray(is_array($picker) ? $picker : []);
    }

    /** @param array<array-key, mixed> $picker */
    public static function fromArray(array $picker): self
    {
        $features = is_array($picker['features'] ?? null) ? $picker['features'] : [];
        $int = static fn (string $key, int $default, int $min, int $max): int => is_int($picker[$key] ?? null) ? max($min, min($max, $picker[$key])) : $default;
        $bool = static fn (string $key, bool $default): bool => is_bool($picker[$key] ?? null) ? $picker[$key] : $default;
        $one = static fn (string $key, array $allowed, string $default): string => in_array($picker[$key] ?? null, $allowed, true) ? (string) $picker[$key] : $default;

        return new self(
            features: array_combine(self::FEATURES, array_map(
                static fn (string $name): bool => is_bool($features[$name] ?? null) ? $features[$name] : ! in_array($name, self::OPT_IN, true),
                self::FEATURES,
            )),
            placement: $one('placement', self::PLACEMENTS, 'auto'),
            offset: $int('offset', 8, 0, 64),
            arrow: $bool('arrow', true),
            sheetBreakpoint: $int('sheet_breakpoint', 640, 0, 4096),
            columns: $int('columns', 8, 1, 24),
            maxRecent: $int('max_recent', 36, 0, 500),
            sort: $one('sort', ['default', 'name', 'newest'], 'default'),
            recentOrder: $one('recent_order', ['recent', 'frequent'], 'recent'),
            closeOnSelect: $bool('close_on_select', true),
            trigger: is_string($picker['trigger'] ?? null) && $picker['trigger'] !== '' ? $picker['trigger'] : '🙂',
            theme: $one('theme', ['auto', 'light', 'dark'], 'auto'),
            delivery: $one('delivery', ['auto', 'inline', 'api'], 'auto'),
            render: $one('render', ['auto', 'native', 'image'], 'auto'),
            imageSet: is_string($picker['image_set'] ?? null) && $picker['image_set'] !== '' ? $picker['image_set'] : null,
        );
    }

    public function enabled(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }
}
