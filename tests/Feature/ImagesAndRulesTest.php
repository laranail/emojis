<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;
use Simtabi\Laranail\Emojis\Core\Image\ImageStore;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidImage;
use Simtabi\Laranail\Emojis\Laravel\Rules\EmojiImageRule;

const PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

// The store is tested in tests/Unit/ImageStoreTest.php; these pin what the command reports and returns.
function bindOfflineStore(): string
{
    $root = sys_get_temp_dir() . '/laranail-emojis-images-' . bin2hex(random_bytes(4));
    app()->instance(ImageStore::class, new ImageStore(app(Emojis::class), $root, static fn (array $urls): array => array_fill_keys($urls, null)));

    return $root;
}

it('fails verify for a set that is not installed, naming the install command', function (): void {
    bindOfflineStore();

    expect(Artisan::call('laranail::emojis.images', ['action' => 'verify', 'set' => 'twemoji']))->toBe(1)
        ->and(Artisan::output())->toContain('laranail::emojis.images install twemoji');
});

it('fails install when nothing downloads, and writes nothing', function (): void {
    $root = bindOfflineStore();

    expect(Artisan::call('laranail::emojis.images', ['action' => 'install', 'set' => 'twemoji']))->toBe(1)
        ->and(Artisan::output())->toContain('did not match their pinned hash')
        ->and(glob($root . '/twemoji/*.svg') ?: [])->toBe([]);
});

it('refuses an unknown action or set', function (array $arguments, string $message): void {
    bindOfflineStore();

    expect(Artisan::call('laranail::emojis.images', $arguments))->toBe(1)
        ->and(Artisan::output())->toContain($message);
})->with([
    'action' => [['action' => 'delete', 'set' => 'twemoji'], 'Action must be install or verify.'],
    'set'    => [['action' => 'verify', 'set' => 'no-such-set'], 'no-such-set'],
]);

it('validates emoji images from a data URI, a file or a URL, and reports why one fails', function (mixed $value, bool $passes): void {
    expect(Validator::make(['v' => $value], ['v' => [new EmojiImageRule]])->passes())->toBe($passes);
})->with([
    'data URI'        => ['data:image/png;base64,' . PIXEL_PNG, true],
    'https URL'       => ['https://example.com/a.png', true],
    'javascript: URL' => ['javascript:alert(1)', false],
    'not a string'    => [['a'], false],
]);

it('validates an uploaded emoji image file', function (): void {
    $path = sys_get_temp_dir() . '/laranail-emojis-' . bin2hex(random_bytes(4)) . '.png';
    file_put_contents($path, base64_decode(PIXEL_PNG, true));

    expect(Validator::make(['v' => new SplFileInfo($path)], ['v' => [new EmojiImageRule]])->passes())->toBeTrue();
});

it('accepts strict base64 and refuses anything else', function (): void {
    expect(EmojiImage::fromBase64(PIXEL_PNG)->src)->toStartWith('data:image/png;base64,')
        ->and(static fn (): EmojiImage => EmojiImage::fromBase64(PIXEL_PNG . '!'))->toThrow(InvalidImage::class)
        ->and(static fn (): EmojiImage => EmojiImage::fromBase64(PIXEL_PNG, 'image/gif'))->toThrow(InvalidImage::class);
});
