<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\HtmlString;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Illuminate\View\ComponentAttributeBag;
use Simtabi\Laranail\Emojis\Laravel\View\PickerData;
use Simtabi\Laranail\Emojis\Laravel\View\PickerPayloads;

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
 * Any other attribute (class, id, style, data-*) is passed through to it.
 *
 * Out-of-range options are corrected rather than passed on: a tone outside 0–5 is 0, fewer than one column
 * is 8, and an unknown sort or recent order is the default.
 */
final class Picker extends Component
{
    /** @param list<string> $categories */
    public function __construct(
        private readonly Emojis $emojis,
        private readonly PickerPayloads $payloads,
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
        public ?string $maxVersion = null,
        public ?string $trigger = null,
    ) {}

    /**
     * Blade hands a component its attribute bag only after render() has run, so the markup is built by html(),
     * which the one-line view below calls once the bag exists. The view is a fixed string; nothing from the
     * options is compiled as Blade.
     */
    public function render(): string
    {
        return '{!! $html($attributes) !!}';
    }

    public function html(?ComponentAttributeBag $extra = null): HtmlString
    {
        $extra ??= new ComponentAttributeBag;
        $resolved = $this->emojis->locales()->resolve($this->locale);
        $api = config('laranail.emojis.api.enabled');
        $block = '';
        $source = [];

        if (filter_var($api, FILTER_VALIDATE_BOOLEAN) && app('router')->has('laranail.emojis.api.picker')) {
            // Relative, so it is fetched from the host the page is on (tenant subdomains, www and apex,
            // preview domains), not APP_URL's, which would be cross-origin there.
            $source['source'] = route('laranail.emojis.api.picker', absolute: false);
        } else {
            $source['payload'] = PickerData::elementId($resolved);
            $block = $this->written->block($resolved, $this->payloads);
        }

        $attributes = [
            'data-laranail-emoji-picker' => '',
            // The module fills this element in; a Livewire morph would strip what it added and leave the
            // node marked as mounted, so the picker would vanish after the component's next update.
            'wire:ignore' => '',
            ...$this->dataAttributes($source),
            ...$this->dataAttributes([
                'target'          => $this->target,
                'locale'          => $resolved,
                'categories'      => $this->categories === [] ? null : implode(',', $this->categories),
                'max-recent'      => $this->maxRecent === null ? null : max(0, $this->maxRecent),
                'sort'            => in_array($this->sort, ['name', 'newest'], true) ? $this->sort : null,
                'recent-order'    => $this->recentOrder === 'frequent' ? 'frequent' : null,
                'tone'            => $this->tone === null ? null : ($this->tone >= 0 && $this->tone <= 5 ? $this->tone : 0),
                'columns'         => $this->columns === null ? null : ($this->columns >= 1 ? min($this->columns, 24) : 8),
                'close-on-select' => $this->closeOnSelect ? null : 'false',
                'user-key'        => $this->userKey,
                'max-version'     => $this->maxVersion,
                'trigger'         => $this->trigger,
                'strings'         => json_encode($this->payloads->strings($resolved), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]),
            ...($this->inline ? ['data-laranail-emoji-inline' => ''] : []),
        ];

        // Passed-through attributes never override the picker's own data-laranail-emoji-* options.
        foreach ($extra->getAttributes() as $name => $value) {
            if (! isset($attributes[$name]) && is_scalar($value) && $value !== false) {
                $attributes[$name] = $value === true ? '' : (string) $value;
            }
        }

        $html = implode(' ', array_map(static fn (string $name, string $value): string => $value === '' ? $name : $name . '="' . e($value) . '"', array_keys($attributes), $attributes));
        $fallback = e($this->payloads->noScript($resolved));

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
