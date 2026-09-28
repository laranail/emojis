<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Image\ImageStore;
use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;
use Simtabi\Laranail\Emojis\Core\Exceptions\ImageSetNotFound;

// A dataset whose twemoji hash manifest names two fixture files, so install() runs end to end without a
// network: the fetcher is a map from URL to body.

const ROCKET_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36"><path d="M1 1h34v34z" fill="#c00"/></svg>';
const WAVE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36" onload="alert(1)"><script>x</script><circle cx="18" cy="18" r="9"/></svg>';

/** @return array{0: Emojis, 1: string, 2: array<string, string>} emojis, image root, url => body */
function storeFixture(): array
{
    $source = dirname(__DIR__, 2) . '/database/generated';
    $dataset = sys_get_temp_dir() . '/laranail-emojis-store-' . bin2hex(random_bytes(4));
    mkdir($dataset . '/image-hashes', 0o755, true);

    foreach (glob($source . '/*') ?: [] as $entry) {
        if (basename($entry) !== 'image-hashes') {
            symlink($entry, $dataset . '/' . basename($entry));
        }
    }

    $hash = static fn (string $body): string => substr(hash('sha256', $body), 0, 32);
    file_put_contents($dataset . '/image-hashes/twemoji.php', '<?php return ' . var_export(['version' => 'fixture', 'hashes' => ['1F680' => $hash(ROCKET_SVG), '1F44B' => $hash(WAVE_SVG)]], true) . ';');

    $emojis = Emojis::create(data: new DatasetStore($dataset));
    $cdn = $emojis->images()->upstream('twemoji');
    $bodies = [
        (string) $cdn->url($emojis->get('🚀')) => ROCKET_SVG,
        (string) $cdn->url($emojis->get('👋')) => WAVE_SVG,
    ];

    return [$emojis, $dataset . '/public-images', $bodies];
}

it('installs only files whose hash matches, sanitised, and records what it wrote', function (): void {
    [$emojis, $root, $bodies] = storeFixture();
    $store = new ImageStore($emojis, $root, static fn (array $urls): array => array_map(static fn (string $u): ?string => $bodies[$u] ?? null, array_combine($urls, $urls)));

    $result = $store->install('twemoji');

    expect($result)->toMatchArray(['written' => 2, 'kept' => 0, 'failed' => []])
        ->and(file_get_contents($root . '/twemoji/1f680.svg'))->toContain('<path d="M1 1h34v34z"')
        ->and(file_get_contents($root . '/twemoji/1f44b.svg'))->not->toContain('script')->not->toContain('onload')
        ->and($store->installed('twemoji'))->toBeTrue()
        ->and($store->verify('twemoji'))->toMatchArray(['ok' => 2, 'changed' => [], 'missing' => []])
        ->and($store->install('twemoji'))->toMatchArray(['written' => 0, 'kept' => 2]);
});

it('refuses a download that differs from the pinned file, and writes nothing for it', function (): void {
    [$emojis, $root, $bodies] = storeFixture();
    $tampered = array_map(static fn (string $body): string => str_replace('#c00', '#0c0', $body), $bodies);
    $store = new ImageStore($emojis, $root, static fn (array $urls): array => array_map(static fn (string $u): ?string => $tampered[$u] ?? null, array_combine($urls, $urls)));

    $result = $store->install('twemoji');

    expect($result['failed'])->toBe(['1f680.svg'])
        ->and(is_file($root . '/twemoji/1f680.svg'))->toBeFalse()
        ->and(is_file($root . '/twemoji/1f44b.svg'))->toBeTrue();
});

it('detects a file changed on disk after installation', function (): void {
    [$emojis, $root, $bodies] = storeFixture();
    $store = new ImageStore($emojis, $root, static fn (array $urls): array => array_map(static fn (string $u): ?string => $bodies[$u] ?? null, array_combine($urls, $urls)));
    $store->install('twemoji');

    file_put_contents($root . '/twemoji/1f680.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>x</script></svg>');
    unlink($root . '/twemoji/1f44b.svg');

    expect($store->verify('twemoji'))->toMatchArray(['ok' => 0, 'changed' => ['1f680.svg'], 'missing' => ['1f44b.svg']]);
});

it('ships verification hashes for the four redistributable sets only', function (): void {
    $store = emojis()->dataset();

    foreach (['twemoji', 'noto', 'openmoji', 'fluent'] as $set) {
        expect(count($store->imageHashes($set)))->toBeGreaterThan(2500);
    }

    expect($store->imageHashes('joypixels'))->toBe([])
        ->and($store->imageHashes('../emojis'))->toBe([])
        ->and(static fn (): array => new ImageStore(emojis(), sys_get_temp_dir(), static fn (array $u): array => [])->install('joypixels'))
        ->toThrow(ImageSetNotFound::class, 'cannot be installed');
});
