<?php

declare(strict_types=1);

/**
 * Proves src/Core runs with nothing but itself, psr/log and PHP.
 *
 * Composer's autoloader would happily load Illuminate for a Core class that reached for it, so a smoke test
 * run through vendor/autoload.php proves nothing about isolation. This installs its own autoloader that maps
 * only the Core namespace and psr/log, and throws on any other class — then drives the public API end to end.
 * A Core class that touches the framework fails here with the class it tried to load.
 */
$root = dirname(__DIR__);
$loaded = [];

spl_autoload_register(static function (string $class) use ($root, &$loaded): void {
    $map = [
        'Simtabi\\Laranail\\Emojis\\Core\\' => $root . '/src/Core/',
        'Psr\\Log\\'                        => $root . '/vendor/psr/log/src/',
    ];

    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

            if (is_file($file)) {
                require $file;
                $loaded[] = $class;

                return;
            }
        }
    }

    fwrite(STDERR, "core-isolation: Core tried to load {$class}, which is outside Core and psr/log.\n");

    exit(1);
});

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;

// Load every Core file first: a class that extends or implements something outside Core fails here even if
// no check below reaches it. Method bodies are only proven by running them, hence the broad checks after.
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src/Core', FilesystemIterator::SKIP_DOTS));
$declared = 0;

foreach ($files as $file) {
    if (str_ends_with((string) $file, '.php')) {
        $class = 'Simtabi\\Laranail\\Emojis\\Core\\' . str_replace(['/', '.php'], ['\\', ''], substr((string) $file, strlen($root . '/src/Core/')));

        if (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) {
            $declared++;
        }
    }
}

if ($declared < 40) {
    fwrite(STDERR, "core-isolation: only {$declared} Core types declared; the directory walk is broken.\n");

    exit(1);
}

$emojis = Emojis::create(['locale' => 'fr'], terminal: new EnvTerminalProbe(override: true), data: new DatasetStore($root . '/resources/data'));

$checks = [
    'emoji'    => [$emojis->text('Ship it :rocket: :)')->withEmoticons()->toEmoji(), 'Ship it 🚀 🙂'],
    'ascii'    => [$emojis->text('👋🏽 🇰🇪')->toAscii(), ':wave_tone3: :kenya:'],
    'name'     => [$emojis->get('rocket')->name(), 'fusée'],
    'tone'     => [(string) $emojis->get('handshake')->withSkinTone(SkinTone::Light, SkinTone::Dark), '🫱🏻‍🫲🏿'],
    'auto'     => [$emojis->text(':wave:')->to(Mode::Auto), '👋'],
    'strip'    => [$emojis->strip('a 🐱‍👤 b'), 'a  b'],
    'flag'     => [(string) $emojis->flag('KE'), '🇰🇪'],
    'query'    => [(string) $emojis->query()->flags()->count(), (string) $emojis->query()->type(Simtabi\Laranail\Emojis\Core\Enums\SequenceType::Flag, Simtabi\Laranail\Emojis\Core\Enums\SequenceType::Tag)->count()],
    'search'   => [(string) $emojis->search('fusée', 'fr')->first(), '🚀'],
    'kaomoji'  => [(string) count($emojis->kaomojiGroups()), '15'],
    'html'     => [$emojis->html('<code>:wave:</code> :wave:')->toEmoji(), '<code>:wave:</code> 👋'],
    'entities' => [$emojis->text('&#x1F680;')->from(Mode::HtmlEntity)->toCodepoints(), 'U+1F680'],
    'width'    => [(string) $emojis->text('a👨‍👩‍👧‍👦')->width(), '3'],
    'custom'   => [$emojis->addCustom('lara', 'https://example.com/l.svg')->text(':lara:')->toShortcodes(), ':lara:'],
];

foreach (['twemoji', 'noto', 'openmoji', 'fluent', 'joypixels'] as $set) {
    $checks["image:{$set}"] = [$emojis->get('😀')->imageUrl($set) === null ? 'none' : 'url', 'url'];
}

$failed = false;

foreach ($checks as $name => [$actual, $expected]) {
    if ($actual !== $expected) {
        fwrite(STDERR, "core-isolation [{$name}]: expected {$expected}, got {$actual}\n");
        $failed = true;
    }
}

if (! $failed && str_contains((string) $emojis->text('🚀')->toImages(), 'twemoji') === false) {
    fwrite(STDERR, "core-isolation [image]: no image URL\n");
    $failed = true;
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, sprintf("core-isolation: %d Core types declared, %d checks run, %d classes loaded, none outside Core and psr/log.\n", $declared, count($checks), count($loaded)));

exit(0);
