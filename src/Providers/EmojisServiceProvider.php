<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Providers;

use Closure;
use Override;
use InvalidArgumentException;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Options;
use Simtabi\Laranail\Package\Tools\Package;
use Illuminate\Contracts\Foundation\Application;
use Simtabi\Laranail\Emojis\Console\ShowCommand;
use Simtabi\Laranail\Emojis\Console\ExportCommand;
use Simtabi\Laranail\Emojis\Console\ImagesCommand;
use Simtabi\Laranail\Emojis\Console\SearchCommand;
use Simtabi\Laranail\Emojis\Core\Image\ImageStore;
use Simtabi\Laranail\Emojis\Core\Render\ImageSets;
use Simtabi\Laranail\Emojis\Console\ConvertCommand;
use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;
use Simtabi\Laranail\Emojis\Core\Enums\ImageSource;
use Simtabi\Laranail\Emojis\Laravel\BladeDirective;
use Simtabi\Laranail\Emojis\Console\SanitizeCommand;
use Simtabi\Laranail\Emojis\Core\Enums\ImageSetName;
use Simtabi\Laranail\Emojis\Core\Image\HttpsFetcher;
use Simtabi\Laranail\Emojis\Core\Contracts\HtmlFactory;
use Simtabi\Laranail\Emojis\Core\Contracts\EmojisFluent;
use Simtabi\Laranail\Emojis\Laravel\Doctor\DatasetCheck;
use Simtabi\Laranail\Emojis\Core\Contracts\TerminalProbe;
use Simtabi\Laranail\Emojis\Laravel\ConsoleTerminalProbe;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Emojis\Laravel\IlluminateHtmlFactory;
use Simtabi\Laranail\Emojis\Core\Contracts\FailureReporter;
use Simtabi\Laranail\Emojis\Laravel\LaravelFailureReporter;
use Simtabi\Laranail\Emojis\Laravel\View\Components\Styles;
use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;
use Simtabi\Laranail\Emojis\Core\Exceptions\ImageSetNotFound;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmojiRegistry;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Entry point for laranail/emojis.
 *
 * Names, all vendor-scoped and asserted against the live registries by NamingConventionTest:
 * config `laranail.emojis` (published to config/laranail/emojis.php, tag `laranail::emojis-config`),
 * translations `laranail/emojis::`, Blade tag `<x-laranail-emojis::emoji />`, directive
 * `@laranailEmojis(...)`, commands `laranail::emojis.*`.
 *
 * Emojis is a worker-wide singleton. That is safe under Octane because every registration is frozen once
 * the application has booted, and the locale is read from the application on each call rather than
 * captured here.
 *
 * @internal Auto-discovered framework wiring; not part of the public API.
 */
final class EmojisServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/emojis')
            ->setPublishTagId('emojis')
            ->hasConfigFile('emojis')
            ->hasTranslations()
            // The Vite build (public/assets/{css,js}) → public/vendor/laranail/emojis, tag laranail::emojis-assets.
            ->publishDirectory('public/assets', public_path(Styles::PUBLISHED_PATH), 'assets')
            ->hasBladeComponentNamespace('Simtabi\\Laranail\\Emojis\\Laravel\\View\\Components', 'laranail-emojis')
            ->hasBladeDirective('laranailEmojis', static fn (string $expression): string => BladeDirective::compile($expression))
            ->hasCommands([
                SearchCommand::class,
                ShowCommand::class,
                ConvertCommand::class,
                ExportCommand::class,
                SanitizeCommand::class,
                ImagesCommand::class,
            ])
            ->hasDoctorCheck(DatasetCheck::class)
            ->hasAboutSection('Emojis', fn (): array => $this->aboutSection());
    }

    #[Override]
    public function packageRegistered(): void
    {
        // The shipped dataset, shared with Emojis::create(): a store per booted application re-parses the
        // shards again wherever opcache is off, which includes every CLI process by default.
        $this->app->singleton(DatasetStore::class, static fn (): DatasetStore => DatasetStore::packaged());
        $this->app->singleton(CustomEmojiRegistry::class);
        $this->app->singleton(TerminalProbe::class, ConsoleTerminalProbe::class);
        $this->app->singleton(HtmlFactory::class, IlluminateHtmlFactory::class);
        $this->app->singleton(
            FailureReporter::class,
            static fn (Application $app): FailureReporter => new LaravelFailureReporter($app->bound(BootReport::class) ? $app->make(BootReport::class) : null),
        );
        $this->app->singleton(
            ImageSets::class,
            static fn (Application $app): ImageSets => new ImageSets($app->make(DatasetStore::class), self::imageBaseUrls($app)),
        );
        $this->app->singleton(
            ImageStore::class,
            static fn (Application $app): ImageStore => new ImageStore($app->make(Emojis::class), self::imageRoot(), Closure::fromCallable(new HttpsFetcher)),
        );

        $this->app->singleton(Emojis::class, static function (Application $app): Emojis {
            $pinned = $app->make(ConfigRepository::class)->get('laranail.emojis.locale.default');

            return new Emojis(
                data: $app->make(DatasetStore::class),
                options: self::options($app),
                reporter: $app->make(FailureReporter::class),
                terminal: $app->make(TerminalProbe::class),
                html: $app->make(HtmlFactory::class),
                custom: $app->make(CustomEmojiRegistry::class),
                currentLocale: is_string($pinned) && $pinned !== '' ? null : $app->getLocale(...),
                images: $app->make(ImageSets::class),
            );
        });

        $this->app->alias(Emojis::class, EmojisFluent::class);
    }

    #[Override]
    public function packageBooted(): void
    {
        // Critical: a configured image set that does not exist would render every emoji through the fallback
        // chain and report success. Checked at boot so it fails where it is caused, not on the first page view.
        FailurePolicy::run(function (): void {
            $emojis = $this->app->make(Emojis::class);
            $set = $emojis->options()->imageSet;

            if (! $emojis->images()->has($set)) {
                throw ImageSetNotFound::named($set, $emojis->images()->names());
            }

            $this->registerConfiguredExtensions($emojis);
        }, 'laranail/emojis:configuration', BootCriticality::Critical);

        // Degradable: a set configured for local serving but not installed yet keeps loading from the CDN.
        // Not critical, because the command that installs it boots this provider too.
        FailurePolicy::run(function (): void {
            $options = $this->app->make(Emojis::class)->options();

            if ($options->imageSource === ImageSource::Local && ! is_file(self::imageRoot() . '/' . $options->imageSet . '/' . ImageStore::MANIFEST)) {
                throw new InvalidArgumentException("images.source is local but {$options->imageSet} is not installed; run laranail::emojis.images install {$options->imageSet}. Serving it from the CDN meanwhile.");
            }
        }, 'laranail/emojis:local-images', BootCriticality::Degradable);

        $this->app->booted(fn (): Emojis => $this->app->make(Emojis::class)->freeze());
    }

    private static function options(Application $app): Options
    {
        $config = (array) $app->make(ConfigRepository::class)->get('laranail.emojis', []);
        $locale = is_array($config['locale'] ?? null) ? $config['locale'] : [];
        $locale['default'] = is_string($locale['default'] ?? null) && $locale['default'] !== '' ? $locale['default'] : $app->getLocale();
        $config['locale'] = is_string($config['locale'] ?? null) ? $config['locale'] : $locale;

        /** @var array<string, mixed> $config */
        return Options::fromArray($config);
    }

    /**
     * Configured overrides, plus — with `images.source` local — the application's own copy of every built-in
     * set that has been installed. A set that has not been installed keeps its CDN address.
     *
     * @return array<string, string>
     */
    private static function imageBaseUrls(Application $app): array
    {
        $options = self::options($app);
        $urls = $options->imageBaseUrls;

        if ($options->imageSource === ImageSource::Local) {
            foreach (ImageSetName::cases() as $set) {
                if (! isset($urls[$set->value]) && is_file(self::imageRoot() . '/' . $set->value . '/' . ImageStore::MANIFEST)) {
                    $urls[$set->value] = asset(ImageStore::publicPath($set->value));
                }
            }
        }

        return $urls;
    }

    private static function imageRoot(): string
    {
        return public_path('vendor/laranail/emojis/images');
    }

    private function registerConfiguredExtensions(Emojis $emojis): void
    {
        $config = $this->app->make(ConfigRepository::class);

        foreach ((array) $config->get('laranail.emojis.extend.custom', []) as $name => $custom) {
            $custom = (array) $custom;

            if (array_key_exists('url', $custom)) {
                throw new InvalidArgumentException("laranail.emojis.extend.custom.{$name}.url was renamed to image in 0.2.0; it now also accepts a data URI.");
            }

            $emojis->addCustom(
                (string) $name,
                (string) ($custom['image'] ?? ''),
                isset($custom['fallback']) ? (string) $custom['fallback'] : null,
                array_values(array_map(strval(...), (array) ($custom['aliases'] ?? []))),
                isset($custom['label']) ? (string) $custom['label'] : null,
            );
        }

        foreach ((array) $config->get('laranail.emojis.extend.images', []) as $emoji => $image) {
            $emojis->useImage((string) $emoji, (string) $image);
        }

        foreach ((array) $config->get('laranail.emojis.extend.shortcodes', []) as $code => $emoji) {
            $emojis->addShortcode((string) $code, (string) $emoji);
        }

        foreach ((array) $config->get('laranail.emojis.extend.emoticons', []) as $emoticon => $emoji) {
            $emojis->addEmoticon((string) $emoticon, (string) $emoji);
        }
    }

    /** @return array<string, string> */
    private function aboutSection(): array
    {
        $emojis = $this->app->make(Emojis::class);

        return [
            'Dataset'          => $emojis->datasetVersion(),
            'Locales'          => (string) count($emojis->availableLocales()),
            'Image set'        => $emojis->options()->imageSet,
            'Shortcode preset' => $emojis->options()->preset->value,
        ];
    }
}
