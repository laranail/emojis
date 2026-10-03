<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\HtmlString;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Illuminate\View\ComponentAttributeBag;
use Simtabi\Laranail\Emojis\Laravel\View\PickerData;
use Simtabi\Laranail\Emojis\Laravel\View\PickerConfig;
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
        private readonly PickerConfig $config,
        public ?string $target = null,
        public ?string $locale = null,
        public bool $inline = false,
        public array $categories = [],
        public ?int $maxRecent = null,
        public ?string $sort = null,
        public ?string $recentOrder = null,
        public ?int $tone = null,
        public ?int $columns = null,
        public ?bool $closeOnSelect = null,
        public ?string $userKey = null,
        public ?string $maxVersion = null,
        public ?string $trigger = null,
        public ?string $placement = null,
        public ?int $offset = null,
        public ?bool $arrow = null,
        public ?int $sheetBreakpoint = null,
        public ?string $theme = null,
        public ?string $render = null,
        /** @var array<string, bool> feature name => on, over `picker.features` */
        public array $features = [],
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
        $c = $this->config;
        $api = filter_var(config('laranail.emojis.api.enabled'), FILTER_VALIDATE_BOOLEAN) && app('router')->has('laranail.emojis.api.picker');
        $block = '';
        $source = [];

        // delivery: inline always embeds; api and auto fetch when the API is on. api without the API falls back
        // to inline (the doctor reports it) rather than rendering a picker that cannot load.
        if ($api && $c->delivery !== 'inline') {
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
                'target'     => $this->target,
                'locale'     => $resolved,
                'categories' => $this->categories === [] ? null : implode(',', $this->categories),
                // Each option is the attribute, else the config default; written only when it differs from the
                // module's own default, so the markup stays short.
                'max-recent'       => $this->differs(max(0, $this->maxRecent ?? $c->maxRecent), 36),
                'sort'             => $this->differs(in_array($this->sort, ['name', 'newest', 'default'], true) ? $this->sort : $c->sort, 'default'),
                'recent-order'     => $this->differs(in_array($this->recentOrder, ['frequent', 'recent'], true) ? $this->recentOrder : $c->recentOrder, 'recent'),
                'tone'             => $this->tone === null ? null : ($this->tone >= 0 && $this->tone <= 5 ? $this->tone : 0),
                'columns'          => $this->differs($this->columns === null ? $c->columns : ($this->columns >= 1 ? min($this->columns, 24) : 8), 8),
                'close-on-select'  => ($this->closeOnSelect ?? $c->closeOnSelect) ? null : 'false',
                'user-key'         => $this->userKey,
                'max-version'      => $this->maxVersion,
                'trigger'          => $this->differs($this->trigger ?? $c->trigger, '🙂'),
                'placement'        => $this->differs(in_array($this->placement, PickerConfig::PLACEMENTS, true) ? $this->placement : $c->placement, 'auto'),
                'offset'           => $this->differs($this->offset ?? $c->offset, 8),
                'arrow'            => ($this->arrow ?? $c->arrow) ? null : 'false',
                'sheet-breakpoint' => $this->differs(max(0, $this->sheetBreakpoint ?? $c->sheetBreakpoint), 640),
                'features'         => $this->featureAttribute(),
                'render'           => $this->differs(in_array($this->render, ['auto', 'native', 'image'], true) ? $this->render : $c->render, 'auto'),
                'strings'          => json_encode($this->payloads->strings($resolved), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]),
            ...($this->inline ? ['data-laranail-emoji-inline' => ''] : []),
            // A fixed theme marks the mount point, which the stylesheet reads as an ancestor.
            ...(($theme = in_array($this->theme, ['auto', 'light', 'dark'], true) ? $this->theme : $c->theme) !== 'auto' ? ['data-theme' => $theme] : []),
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

    private function differs(int|string $value, int|string $default): int|string|null
    {
        return $value === $default ? null : $value;
    }

    /**
     * The features that differ from the module's defaults, as JSON, or null when none does: every one it
     * has is on unless listed, except the set switcher, which is off unless listed. Kaomoji and symbols need
     * no flag here: the payload carries them only when they are on.
     */
    private function featureAttribute(): ?string
    {
        $flags = [];

        // The switcher is the one opt-in feature the module also needs to know about.
        if (is_bool($this->features['set_switcher'] ?? null) ? $this->features['set_switcher'] : $this->config->enabled('set_switcher')) {
            $flags['setSwitcher'] = true;
        }

        foreach (PickerConfig::FEATURES as $name) {
            $on = is_bool($this->features[$name] ?? null) ? $this->features[$name] : $this->config->enabled($name);

            if (! $on && ! in_array($name, PickerConfig::OPT_IN, true)) {
                $flags[lcfirst(str_replace('_', '', ucwords($name, '_')))] = false;
            }
        }

        return $flags === [] ? null : json_encode($flags, JSON_THROW_ON_ERROR);
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
