<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View;

use Throwable;
use Composer\InstalledVersions;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Illuminate\Contracts\Translation\Translator;
use Simtabi\Laranail\Emojis\Core\Picker\PickerPayload;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmoji;
use Simtabi\Laranail\Emojis\Core\Picker\PayloadBuilder;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * The picker payload and interface strings for one locale, as the Blade picker, its data block and the HTTP
 * API all serve them: built by PayloadBuilder, with the group names translated, and cached.
 *
 * Building walks the whole catalogue, so it is cached per locale under a key that changes whenever anything
 * the payload depends on does: the package's installed version, the dataset version, the policy, the
 * shortcode preset and delimiters, the active emoticons, the custom emoji and the translated group names. A
 * stale payload cannot be served, and nothing needs clearing after a config change. It is also kept in
 * memory for the rest of the request (or Octane worker life, which the key makes safe).
 *
 * The cache holds the payload's fields as a plain array, not the object, so a store configured with
 * cache.serializable_classes = false still hits instead of handing back an incomplete class.
 */
final class PickerPayloads
{
    /** @var array<string, PickerPayload> */
    private array $built = [];

    private readonly PickerConfig $config;

    public function __construct(
        private readonly Emojis $emojis,
        private readonly PayloadBuilder $builder,
        private readonly Translator $translator,
        private readonly ?CacheRepository $cache = null,
        ?PickerConfig $config = null,
    ) {
        $this->config = $config ?? PickerConfig::fromArray([]);
    }

    public function payload(?string $locale = null): PickerPayload
    {
        $locale = $this->emojis->locales()->resolve($locale);
        $labels = $this->groupLabels($locale);
        $key = 'laranail.emojis.picker:' . $locale . ':' . $this->fingerprint($labels);

        if (isset($this->built[$key])) {
            return $this->built[$key];
        }

        $build = fn (): PickerPayload => $this->withoutCustomUnlessEnabled($this->builder->build(
            $locale,
            $labels,
            kaomoji: $this->config->enabled('kaomoji'),
            symbols: $this->config->enabled('symbols'),
            imageSet: $this->imageSet(),
            switchSets: $this->config->enabled('set_switcher') ? PickerConfig::SWITCHABLE : [],
            englishKeywords: $this->config->enabled('english_keywords'),
        ));

        if (! $this->cache instanceof CacheRepository) {
            return $this->built[$key] = $build();
        }

        try {
            $payload = $this->rehydrate($this->cache->remember($key, 86_400, static fn (): array => get_object_vars($build())));

            if (! $payload instanceof PickerPayload) {
                // An entry this code cannot read (another version's, or corrupted) would otherwise be rebuilt
                // around on every request until it expires: replace it.
                $payload = $build();
                $this->cache->put($key, get_object_vars($payload), 86_400);
            }
        } catch (Throwable) {
            // A cache that cannot hold the payload (too large for a memcached item, a store that is down)
            // must not take the picker with it.
            $payload = $build();
        }

        return $this->built[$key] = $payload;
    }

    /**
     * The picker's interface strings in a locale, keyed as the JavaScript reads them, English for any the
     * locale lacks.
     *
     * @return array<string, string|list<string>>
     */
    public function strings(?string $locale = null): array
    {
        $locale = $this->emojis->locales()->resolve($locale);
        $all = $this->lines($locale);
        $text = static fn (string $key): ?string => is_string($all[$key] ?? null) ? $all[$key] : null;
        $tones = $all['tones'] ?? null;

        return array_filter([
            'search'     => $text('search'),
            'results'    => $text('results'),
            'resultsOne' => $text('results_one'),
            'noResults'  => $text('no_results'),
            'recent'     => $text('recent'),
            'custom'     => $text('custom'),
            'tone'       => $text('tone'),
            // Six names or none: a short list would leave tone buttons without a label.
            'tones'        => is_array($tones) && count($tones) === 6 && array_filter($tones, is_string(...)) === $tones ? array_values($tones) : null,
            'open'         => $text('open'),
            'loading'      => $text('loading'),
            'failed'       => $text('failed'),
            'emoji'        => $text('emoji'),
            'kaomoji'      => $text('kaomoji'),
            'symbols'      => $text('symbols'),
            'style'        => $text('style'),
            'native'       => $text('native'),
            'settings'     => $text('settings'),
            'theme'        => $text('theme'),
            'themeAuto'    => $text('theme_auto'),
            'themeLight'   => $text('theme_light'),
            'themeDark'    => $text('theme_dark'),
            'clearRecents' => $text('clear_recents'),
            'kbdShortcuts' => $text('kbd_shortcuts'),
            'kbdOpen'      => $text('kbd_open'),
            'kbdSearch'    => $text('kbd_search'),
            'kbdCategory'  => $text('kbd_category'),
            'kbdMove'      => $text('kbd_move'),
            'kbdTone'      => $text('kbd_tone'),
            'kbdClose'     => $text('kbd_close'),
        ], static fn (mixed $v): bool => $v !== null);
    }

    /** The text shown where the picker would be when JavaScript is off. */
    public function noScript(?string $locale = null): string
    {
        $line = $this->lines($this->emojis->locales()->resolve($locale))['no_script'] ?? '';

        return is_string($line) ? $line : '';
    }

    /** The payload from its cached fields, or null when the cache handed back something else. */
    private function rehydrate(mixed $fields): ?PickerPayload
    {
        if (! is_array($fields)) {
            return null;
        }

        try {
            return new PickerPayload(...$fields);
        } catch (Throwable) {
            return null;
        }
    }

    /** The installed package's commit, so an upgrade never serves a payload an older version built. */
    private function packageVersion(): string
    {
        return class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('laranail/emojis')
            ? (InstalledVersions::getReference('laranail/emojis') ?? InstalledVersions::getPrettyVersion('laranail/emojis') ?? '')
            : '';
    }

    /** The set a picker draws images from: none when it draws only native emoji. */
    private function imageSet(): ?string
    {
        return $this->config->render === 'native' && ! $this->config->enabled('set_switcher') ? null : ($this->config->imageSet ?? $this->emojis->options()->imageSet);
    }

    /** The custom emoji are left out when `picker.features.custom` is off, whatever the policy allows. */
    private function withoutCustomUnlessEnabled(PickerPayload $payload): PickerPayload
    {
        if ($this->config->enabled('custom') || $payload->custom === []) {
            return $payload;
        }

        return new PickerPayload($payload->dataset, $payload->locale, $payload->groups, [], $payload->shortcodeOpen, $payload->shortcodeClose, $payload->kaomoji, $payload->symbols, $payload->images, $payload->imageSets);
    }

    /**
     * The picker translation group in a locale. trans() returns the key itself when a group is missing in
     * both the locale and the fallback; that string is not an array of lines, so it reads as none.
     *
     * @return array<array-key, mixed>
     */
    private function lines(string $locale): array
    {
        $lines = $this->translator->get('laranail/emojis::picker', [], $locale);

        return is_array($lines) ? $lines : [];
    }

    /** @return array<string, string> */
    private function groupLabels(string $locale): array
    {
        $groups = $this->lines($locale)['groups'] ?? null;

        return is_array($groups) ? array_filter($groups, static fn (mixed $label, mixed $slug): bool => is_string($label) && is_string($slug), ARRAY_FILTER_USE_BOTH) : [];
    }

    /** @param array<string, string> $labels */
    private function fingerprint(array $labels): string
    {
        $options = $this->emojis->options();

        return hash('xxh128', serialize([
            $this->packageVersion(),
            $this->emojis->datasetVersion(),
            $options->policy,
            $options->preset,
            $options->shortcodeOpen,
            $options->shortcodeClose,
            // extend.emoticons and input.disabled_emoticons: the payload lists each emoji's emoticons.
            $this->emojis->catalogue()->activeEmoticons(risky: true),
            array_map(static fn (CustomEmoji $c): array => [$c->name, $c->label(), $c->image->src, $c->fallback], $this->emojis->customEmojis()),
            $labels,
            $this->config->features,
            $this->config->render,
            $this->imageSet(),
            $this->emojis->options()->imageBaseUrls,
        ]));
    }
}
