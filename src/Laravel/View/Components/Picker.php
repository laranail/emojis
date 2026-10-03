<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\HtmlString;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Laravel\View\PickerData;
use Simtabi\Laranail\Emojis\Core\Picker\PayloadBuilder;

/**
 * <x-laranail-emojis::picker target="#message" />
 * <x-laranail-emojis::picker target="#message" locale="fr" inline :categories="['recent', 'smileys_and_emotion']" />
 *
 * Renders the mount point the picker module (<x-laranail-emojis::scripts />) fills in, with every option as a
 * data-laranail-emoji-* attribute. The emoji come from the HTTP API when it is enabled, which is cacheable and
 * keeps the page small; otherwise the payload is embedded once per locale per request as a JSON data block,
 * escaped so it cannot close its <script> element. Either way PayloadBuilder decides what is offered, so the
 * picker never shows an emoji the configured policy refuses.
 *
 * Without JavaScript the target still accepts typed emoji, and the mount point says so. The mount point
 * carries wire:ignore, so it survives inside a Livewire component; outside Livewire the attribute is inert.
 */
final class Picker extends Component
{
    /** @param list<string> $categories */
    public function __construct(
        private readonly Emojis $emojis,
        private readonly PayloadBuilder $builder,
        private readonly PickerData $written,
        public ?string $target = null,
        public ?string $locale = null,
        public bool $inline = false,
        public array $categories = [],
        public ?int $maxRecent = null,
        public string $sort = 'default',
        public string $recentOrder = 'recent',
        public ?int $tone = null,
        public ?int $columns = null,
        public bool $closeOnSelect = true,
        public ?string $userKey = null,
    ) {}

    public function render(): HtmlString
    {
        $locale = $this->emojis->locales()->resolve($this->locale);
        $strings = (array) trans('laranail/emojis::picker', [], $locale);
        $api = config('laranail.emojis.api.enabled');
        $block = '';
        $source = [];

        if (filter_var($api, FILTER_VALIDATE_BOOLEAN) && app('router')->has('laranail.emojis.api.picker')) {
            $source['source'] = route('laranail.emojis.api.picker');
        } else {
            $id = 'laranail-emoji-picker-data-' . $locale;
            $source['payload'] = $id;

            if ($this->written->claim($locale)) {
                $json = json_encode($this->builder->build($locale), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $block = '<script type="application/json" id="' . e($id) . "\">{$json}</script>";
            }
        }

        $attributes = [
            'data-laranail-emoji-picker' => '',
            // The module fills this element in; a Livewire morph would strip what it added and leave the
            // node marked as mounted, so the picker would vanish after the component's next update.
            'wire:ignore' => '',
            ...$this->dataAttributes($source),
            ...$this->dataAttributes([
                'target'          => $this->target,
                'locale'          => $locale,
                'categories'      => $this->categories === [] ? null : implode(',', $this->categories),
                'max-recent'      => $this->maxRecent,
                'sort'            => $this->sort === 'default' ? null : $this->sort,
                'recent-order'    => $this->recentOrder === 'recent' ? null : $this->recentOrder,
                'tone'            => $this->tone,
                'columns'         => $this->columns,
                'close-on-select' => $this->closeOnSelect ? null : 'false',
                'user-key'        => $this->userKey,
                'strings'         => json_encode(array_filter([
                    'search' => $strings['search'] ?? null, 'results' => $strings['results'] ?? null, 'noResults' => $strings['no_results'] ?? null,
                    'recent' => $strings['recent'] ?? null, 'custom' => $strings['custom'] ?? null, 'tone' => $strings['tone'] ?? null,
                    'tones'  => $strings['tones'] ?? null, 'open' => $strings['open'] ?? null, 'loading' => $strings['loading'] ?? null,
                    'failed' => $strings['failed'] ?? null,
                ], static fn (mixed $v): bool => $v !== null), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]),
            ...($this->inline ? ['data-laranail-emoji-inline' => ''] : []),
        ];

        $html = implode(' ', array_map(static fn (string $name, string $value): string => $value === '' ? $name : $name . '="' . e($value) . '"', array_keys($attributes), $attributes));
        $fallback = e(is_string($strings['no_script'] ?? null) ? $strings['no_script'] : '');

        return new HtmlString("{$block}<div {$html}><noscript>{$fallback}</noscript></div>");
    }

    /**
     * @param array<string, string|int|null> $options
     *
     * @return array<string, string>
     */
    private function dataAttributes(array $options): array
    {
        $out = [];

        foreach ($options as $name => $value) {
            if ($value !== null) {
                $out['data-laranail-emoji-' . $name] = (string) $value;
            }
        }

        return $out;
    }
}
