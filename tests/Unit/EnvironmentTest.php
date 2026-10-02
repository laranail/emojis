<?php

declare(strict_types=1);

use Psr\Log\AbstractLogger;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;
use Simtabi\Laranail\Emojis\Core\Exceptions\DatasetException;
use Simtabi\Laranail\Emojis\Core\Support\Psr3FailureReporter;

it('detects emoji support from the environment', function (array $env, bool $windows, bool $expected): void {
    expect(new EnvTerminalProbe($env, windows: $windows)->supportsEmoji())->toBe($expected);
})->with([
    'utf-8 locale'                    => [['LANG' => 'en_US.UTF-8'], false, true],
    'latin-1 locale'                  => [['LANG' => 'en_US.ISO-8859-1'], false, false],
    'linux console'                   => [['TERM' => 'linux', 'LANG' => 'en_US.UTF-8'], false, false],
    'dumb terminal'                   => [['TERM' => 'dumb', 'LANG' => 'en_US.UTF-8'], false, false],
    'iTerm without locale'            => [['TERM_PROGRAM' => 'iTerm.app'], false, true],
    'windows terminal'                => [['WT_SESSION' => 'abc'], true, true],
    'vs code on windows'              => [['TERM_PROGRAM' => 'vscode'], true, true],
    'legacy conhost'                  => [['LANG' => 'en_US.UTF-8'], true, false],
    'forced on'                       => [['LARANAIL_EMOJIS' => '1', 'TERM' => 'dumb'], false, true],
    'forced off'                      => [['LARANAIL_EMOJIS' => '0', 'LANG' => 'en_US.UTF-8'], false, false],
    'no_color is not a glyph setting' => [['NO_COLOR' => '1', 'LANG' => 'en_US.UTF-8'], false, true],
]);

it('shows flags as letters on Windows', function (): void {
    expect(new EnvTerminalProbe(['WT_SESSION' => 'x'], windows: true)->supportsFlags())->toBeFalse()
        ->and(new EnvTerminalProbe(['LANG' => 'C.UTF-8'], windows: false)->supportsFlags())->toBeTrue();
});

it('resolves Mode::Auto through the probe and the configured fallback', function (): void {
    $plain = Emojis::create(['output' => ['auto_fallback' => 'shortcode']], terminal: new EnvTerminalProbe(override: false));
    $rich = Emojis::create(terminal: new EnvTerminalProbe(override: true));

    expect($plain->text('🚀')->to(Mode::Auto))->toBe(':rocket:')
        ->and($rich->text(':rocket:')->to(Mode::Auto))->toBe('🚀');
});

it('falls back through region, script and language', function (string $requested, string $resolved): void {
    expect(emojis()->locales()->resolve($requested))->toBe($resolved);
})->with([
    ['pt_BR', 'pt'],
    ['zh-TW', 'zh-Hant'],
    ['zh_CN', 'zh'],
    ['fr-CA', 'fr'],
    ['xx', 'en'],
]);

it('reports an unshipped locale as a tolerated anomaly, once', function (): void {
    $logger = new class extends AbstractLogger
    {
        /** @var list<array{string, string}> */
        public array $records = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->records[] = [(string) $level, (string) $message];
        }
    };

    $emojis = Emojis::create(reporter: new Psr3FailureReporter($logger));
    $emojis->get('rocket')->name('tlh');
    $emojis->get('rocket')->name('tlh');

    expect($logger->records)->toHaveCount(1)
        ->and($logger->records[0][0])->toBe('warning')
        ->and($emojis->reporter()->degradations())->toBe([]);
});

it('reports each unshipped locale once, not just the first', function (): void {
    $logger = new class extends AbstractLogger
    {
        /** @var list<string> */
        public array $locales = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->locales[] = (string) ($context['locale'] ?? '');
        }
    };

    $emojis = Emojis::create(reporter: new Psr3FailureReporter($logger));

    foreach (['tlh', 'tlh', 'qya', 'tlh', 'qya'] as $locale) {
        $emojis->get('rocket')->name($locale);
    }

    expect($logger->locales)->toBe(['tlh', 'qya']);
});

it('degrades, records and continues when a shipped locale shard is broken', function (): void {
    $dir = sys_get_temp_dir() . '/laranail-emojis-' . bin2hex(random_bytes(4));
    mkdir($dir . '/locales', 0o775, true);

    foreach (glob(dirname(__DIR__, 2) . '/database/generated/*.php') ?: [] as $file) {
        copy($file, $dir . '/' . basename($file));
    }

    file_put_contents($dir . '/locales/fr.php', "<?php return 'not an array';");

    try {
        $emojis = Emojis::create(data: new DatasetStore($dir));

        expect($emojis->get('rocket')->name('fr'))->toBe('rocket')
            ->and($emojis->reporter()->degradations())->toHaveKey('locale-shard');
    } finally {
        array_map(unlink(...), [...(glob($dir . '/locales/*') ?: []), ...(glob($dir . '/*.php') ?: [])]);
        rmdir($dir . '/locales');
        rmdir($dir);
    }
});

it('fails fast when a core shard is missing', function (): void {
    Emojis::create(data: new DatasetStore(sys_get_temp_dir() . '/no-such-emoji-dataset'))->get('rocket');
})->throws(DatasetException::class);

it('shares the packaged dataset between instances', function (): void {
    // One store per process: without opcache (the CLI default) each fresh store re-parses ~7.5 MB.
    expect(DatasetStore::packaged())->toBe(DatasetStore::packaged());

    $before = memory_get_usage();
    $second = Emojis::create();
    $second->text('ship 🚀')->toAscii();

    expect(memory_get_usage() - $before)->toBeLessThan(1_000_000);
});

it('guards reporting: a throwing logger never breaks a conversion', function (): void {
    $logger = new class extends AbstractLogger
    {
        public function log($level, string|Stringable $message, array $context = []): void
        {
            throw new RuntimeException('monitoring is down');
        }
    };

    $previous = ini_set('error_log', '/dev/null');

    try {
        expect(Emojis::create(reporter: new Psr3FailureReporter($logger))->get('rocket')->name('tlh'))->toBe('rocket');
    } finally {
        ini_set('error_log', (string) $previous);
    }
});

it('refuses the 0.1.0 flat config layout with directions instead of silently using defaults', function (array $legacy, string $moved): void {
    Emojis::create($legacy);
})->with([
    'flat key'                    => [['image_set' => 'noto'], 'image_set → images.set'],
    'string locale'               => [['locale' => 'fr'], 'locale → locale.default'],
    'extra shortcodes at the top' => [['shortcodes' => ['shipit' => '1F680']], 'shortcodes → extend.shortcodes'],
])->throws(InvalidArgumentException::class);

it('reads every group of the config', function (): void {
    $options = Emojis::create([
        'locale'     => ['default' => 'fr', 'fallback' => 'de'],
        'shortcodes' => ['preset' => 'slack', 'delimiters' => ['{', '}']],
        'images'     => ['set' => 'noto', 'fit' => 'tight', 'class' => 'e', 'base_urls' => ['noto' => 'https://x.test']],
        'output'     => ['name_template' => '<{name}>', 'auto_fallback' => 'shortcode', 'degradation' => ['text' => ['name']]],
        'input'      => ['max_bytes' => 99],
        'policy'     => ['max_emojis' => 3],
    ])->options();

    expect([$options->locale, $options->fallbackLocale, $options->preset->value, $options->shortcodeOpen . $options->shortcodeClose])->toBe(['fr', 'de', 'slack', '{}'])
        ->and([$options->imageSet, $options->imageFit->value, $options->imageClass, $options->imageBaseUrls])->toBe(['noto', 'tight', 'e', ['noto' => 'https://x.test']])
        ->and([$options->nameTemplate, $options->autoFallback->value, $options->degradationFor(Mode::Text)[0]->value, $options->maxInputBytes])->toBe(['<{name}>', 'shortcode', 'name', 99])
        ->and($options->policy->maxEmojis)->toBe(3);
});
