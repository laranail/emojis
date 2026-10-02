<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Image\ImageType;
use Simtabi\Laranail\Emojis\Core\Image\EmojiImage;
use Simtabi\Laranail\Emojis\Core\Image\ImagePolicy;
use Simtabi\Laranail\Emojis\Core\Image\SvgSanitizer;
use Simtabi\Laranail\Emojis\Core\Image\RasterInspector;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidImage;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidCustomEmoji;

/** A PNG with a real signature and IHDR; the pixel data is irrelevant, the header is what is read. */
function pngOf(int $width, int $height): string
{
    $ihdr = 'IHDR' . pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

    return "\x89PNG\r\n\x1A\n" . pack('N', 13) . $ihdr . pack('N', crc32($ihdr)) . pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));
}

const SAFE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36"><defs><linearGradient id="g"><stop offset="0" stop-color="#fc0"/></linearGradient></defs><circle cx="18" cy="18" r="18" fill="url(#g)" style="stroke:#000;stroke-width:1"/></svg>';

it('accepts a PNG data URI, reads its size from the header and re-encodes it', function (): void {
    $image = EmojiImage::fromDataUri('data:image/png;base64,' . base64_encode(pngOf(64, 48)));

    expect($image->type)->toBe(ImageType::Png)
        ->and([$image->width, $image->height])->toBe([64, 48])
        ->and($image->src)->toStartWith('data:image/png;base64,')
        ->and($image->isEmbedded())->toBeTrue()
        ->and($image->sha256)->toHaveLength(64);
});

it('reads GIF, JPEG and WebP dimensions without decoding pixels', function (): void {
    $gif = 'GIF89a' . pack('vv', 20, 10) . "\x00\x00\x00";
    $jpeg = "\xFF\xD8\xFF\xE0" . pack('n', 16) . str_repeat("\0", 14) . "\xFF\xC0" . pack('n', 17) . "\x08" . pack('nn', 30, 40) . str_repeat("\0", 12);
    $webp = 'RIFF' . pack('V', 30) . 'WEBPVP8X' . pack('V', 10) . "\0\0\0\0" . "\x1F\x00\x00" . "\x0F\x00\x00" . str_repeat("\0", 4);

    expect(RasterInspector::dimensions(ImageType::Gif, $gif))->toBe([20, 10])
        ->and(RasterInspector::dimensions(ImageType::Jpeg, $jpeg))->toBe([40, 30])
        ->and(RasterInspector::dimensions(ImageType::Webp, $webp))->toBe([32, 16]);
});

it('refuses a decompression bomb by its header', function (): void {
    EmojiImage::fromBytes(pngOf(50_000, 50_000));
})->throws(InvalidImage::class, 'wider or taller than 1024');

it('refuses oversized input before decoding it', function (): void {
    EmojiImage::fromDataUri('data:image/png;base64,' . str_repeat('A', 400_000), new ImagePolicy(maxBytes: 1024));
})->throws(InvalidImage::class, 'larger than the 1024-byte limit');

it('trusts the content, not the declared type', function (string $uri, string $message): void {
    expect(static fn (): EmojiImage => EmojiImage::fromDataUri($uri))->toThrow(InvalidImage::class, $message);
})->with([
    'png declared as svg'  => ['data:image/svg+xml;base64,' . base64_encode(pngOf(8, 8)), 'declared as image/svg+xml but its content is image/png'],
    'html declared as png' => ['data:image/png;base64,' . base64_encode('<html><script>alert(1)</script>'), 'not a PNG, GIF, JPEG, WebP or SVG'],
    'bmp'                  => ['data:image/bmp;base64,' . base64_encode('BM' . str_repeat("\0", 60)), 'not a PNG, GIF, JPEG, WebP or SVG'],
    'invalid base64'       => ['data:image/png;base64,***', 'invalid base64'],
    'not a data URI'       => ['data:text/html,<script>', 'not an image data URI'],
    'truncated png header' => ['data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1A\n"), 'png header'],
]);

it('neutralises every active construct in an SVG', function (string $svg): void {
    $clean = EmojiImage::fromBytes($svg);
    $out = (string) base64_decode(substr($clean->src, strlen('data:image/svg+xml;base64,')), true);

    expect($clean->type)->toBe(ImageType::Svg)
        ->and(strtolower($out))->not->toContain('script')
        ->not->toContain('onload')
        ->not->toContain('onclick')
        ->not->toContain('foreignobject')
        ->not->toContain('javascript')
        ->not->toContain('http://evil')
        ->not->toContain('https://evil')
        ->not->toContain('<a ')
        ->not->toContain('<image')
        ->not->toContain('<style')
        ->not->toContain('expression')
        ->not->toContain('<animate')
        ->not->toContain('<set')
        ->not->toContain('xhtml')
        ->not->toContain('data:')
        ->not->toContain('<?xml-stylesheet');
})->with([
    'script'           => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0"/></svg>'],
    'cdata script'     => ['<svg xmlns="http://www.w3.org/2000/svg"><script><![CDATA[alert(1)]]></script></svg>'],
    'event handler'    => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><path onclick="alert(1)" d="M0 0"/></svg>'],
    'foreignObject'    => ['<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div xmlns="http://www.w3.org/1999/xhtml"><script>x</script></div></foreignObject></svg>'],
    'javascript link'  => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="javascript:alert(1)"><path d="M0 0"/></a></svg>'],
    'external use'     => ['<svg xmlns="http://www.w3.org/2000/svg"><use href="https://evil.example/x.svg#a"/></svg>'],
    'data use'         => ['<svg xmlns="http://www.w3.org/2000/svg"><use href="data:image/svg+xml;base64,PHN2Zy8+"/></svg>'],
    'external image'   => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="http://evil.example/track.png"/></svg>'],
    'style element'    => ['<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(https://evil.example/x.css)</style></svg>'],
    'style url'        => ['<svg xmlns="http://www.w3.org/2000/svg"><path style="fill:url(https://evil.example/x)" d="M0 0"/></svg>'],
    'style expression' => ['<svg xmlns="http://www.w3.org/2000/svg"><path style="fill:expression(alert(1))" d="M0 0"/></svg>'],
    'attr url'         => ['<svg xmlns="http://www.w3.org/2000/svg"><path fill="url(http://evil.example/#g)" d="M0 0"/></svg>'],
    'animate href'     => ['<svg xmlns="http://www.w3.org/2000/svg"><a><animate attributeName="href" to="javascript:alert(1)"/><set attributeName="onmouseover" to="alert(1)"/></a></svg>'],
    'stylesheet PI'    => ['<?xml version="1.0"?><?xml-stylesheet href="https://evil.example/x.css"?><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>'],
    'external doctype' => ['<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>'],
]);

it('keeps real emoji art: gradients, fragment references and presentation styles', function (): void {
    $out = SvgSanitizer::sanitize(SAFE_SVG);

    expect($out)->toContain('<linearGradient id="g">')
        ->toContain('fill="url(#g)"')
        ->toContain('style="stroke:#000;stroke-width:1"')
        ->toContain('viewBox="0 0 36 36"');
});

it('refuses entity declarations from callers, and expands only literal ones for pinned upstream files', function (): void {
    $bomb = '<!DOCTYPE svg [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;">]><svg xmlns="http://www.w3.org/2000/svg"><title>&b;</title></svg>';
    $illustrator = '<!DOCTYPE svg [<!ENTITY ns_svg "http://www.w3.org/2000/svg">]><svg xmlns="&ns_svg;"><path d="M0 0"/></svg>';
    $xxe = '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><title>&x;</title></svg>';

    expect(static fn (): string => SvgSanitizer::sanitize($bomb))->toThrow(InvalidImage::class, 'internal subsets')
        ->and(static fn (): string => SvgSanitizer::sanitize($bomb, trusted: true))->toThrow(InvalidImage::class, 'only literal entities')
        ->and(static fn (): string => SvgSanitizer::sanitize($xxe, trusted: true))->toThrow(InvalidImage::class, 'only literal entities')
        ->and(SvgSanitizer::sanitize($illustrator, trusted: true))->toContain('<path d="M0 0"/>');
});

it('refuses a second DOCTYPE or a stray ENTITY left after the first DOCTYPE is dropped', function (string $svg): void {
    expect(static fn (): string => SvgSanitizer::sanitize($svg))->toThrow(InvalidImage::class, 'more than one DOCTYPE')
        ->and(static fn (): string => SvgSanitizer::sanitize($svg, trusted: true))->toThrow(InvalidImage::class, 'more than one DOCTYPE');
})->with([
    'two doctypes' => ['<!DOCTYPE svg><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><title>&x;</title></svg>'],
    'stray entity' => ['<!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg"><!ENTITY x "y"><path d="M0 0"/></svg>'],
]);

it('caps the element count and the nesting depth', function (): void {
    $many = '<svg xmlns="http://www.w3.org/2000/svg">' . str_repeat('<path d="M0 0"/>', 50) . '</svg>';
    $deep = '<svg xmlns="http://www.w3.org/2000/svg">' . str_repeat('<g>', 100) . str_repeat('</g>', 100) . '</svg>';

    expect(static fn (): string => SvgSanitizer::sanitize($many, maxElements: 20))->toThrow(InvalidImage::class, 'more than 20 elements')
        ->and(static fn (): string => SvgSanitizer::sanitize($deep))->toThrow(InvalidImage::class, 'nesting deeper');
});

it('can refuse SVG altogether', function (): void {
    EmojiImage::fromBytes(SAFE_SVG, policy: new ImagePolicy(allowSvg: false));
})->throws(InvalidImage::class, 'disabled');

it('references https and root-relative URLs without fetching them, and refuses everything else', function (): void {
    expect(EmojiImage::fromUrl('https://cdn.example.com/e/rocket.png')->src)->toBe('https://cdn.example.com/e/rocket.png')
        ->and(EmojiImage::fromUrl('/img/rocket.svg')->isEmbedded())->toBeFalse();

    foreach ([
        'http://cdn.example.com/a.png', 'javascript:alert(1)', '//evil.example/a.png', '/\evil.example/a.png',
        "https://cdn.example.com/a.png\n", 'https://user:pw@cdn.example.com/a.png', 'https://cdn.example.com/a b.png',
        'https://cdn.example.com/"onerror="x', 'ftp://cdn.example.com/a.png', '', 'https:///a.png',
    ] as $url) {
        expect(static fn (): EmojiImage => EmojiImage::fromUrl($url))->toThrow(InvalidImage::class);
    }

    expect(static fn (): EmojiImage => EmojiImage::fromUrl('https://other.example/a.png', new ImagePolicy(hosts: ['cdn.example.com'])))->toThrow(InvalidImage::class, 'allowed list');
});

it('never puts the refused input into the exception message', function (): void {
    try {
        EmojiImage::fromDataUri('data:image/png;base64,' . base64_encode('<script>secret-payload</script>'));
    } catch (InvalidImage $e) {
        expect($e->getMessage())->not->toContain('secret-payload');

        return;
    }

    test()->fail('expected InvalidImage');
});

it('renders a custom emoji from a data URI and refuses an unsafe one', function (): void {
    $emojis = Emojis::create()->addCustom('dot', 'data:image/png;base64,' . base64_encode(pngOf(16, 16)));

    expect($emojis->text('ok :dot:')->to(Mode::Image))->toContain('src="data:image/png;base64,')
        ->and(static fn (): Emojis => Emojis::create()->addCustom('evil', 'javascript:alert(1)'))->toThrow(InvalidImage::class)
        ->and(static fn (): Emojis => Emojis::create()->addCustom('big', 'data:image/png;base64,' . base64_encode(pngOf(4096, 16))))->toThrow(InvalidImage::class);
});

it('refuses a custom emoji code that is already taken, as a name or an alias', function (): void {
    $emojis = Emojis::create()->addCustom('laravel', 'https://example.com/l.svg');

    expect(static fn (): Emojis => $emojis->addCustom('other', 'https://example.com/o.svg', aliases: ['laravel']))->toThrow(InvalidCustomEmoji::class, 'already registered')
        ->and(static fn (): Emojis => Emojis::create()->addCustom('zzq', 'https://example.com/x.svg', aliases: ['zzq']))->toThrow(InvalidCustomEmoji::class, 'already registered')
        ->and($emojis->customEmoji('laravel')?->image->src)->toBe('https://example.com/l.svg');
});

it('uses a replacement image for an existing emoji ahead of the image set', function (): void {
    $emojis = Emojis::create()->useImage('thumbsup', 'https://brand.example/thumbs.png');

    expect($emojis->text('ok 👍')->to(Mode::Image))->toContain('src="https://brand.example/thumbs.png"')
        ->and((string) $emojis->get('thumbsup')->toImage())->toContain('https://brand.example/thumbs.png')
        ->and($emojis->text('ok 🚀')->to(Mode::Image))->toContain('jsdelivr');
});

it('validates an image against the configured policy through Emojis::image()', function (): void {
    $emojis = Emojis::create(['images' => ['custom' => ['max_dimension' => 32, 'hosts' => ['cdn.example.com']]]]);

    expect($emojis->image('data:image/png;base64,' . base64_encode(pngOf(32, 32)))->width)->toBe(32)
        ->and(static fn (): EmojiImage => $emojis->image('data:image/png;base64,' . base64_encode(pngOf(33, 32))))->toThrow(InvalidImage::class)
        ->and(static fn (): EmojiImage => $emojis->image('https://elsewhere.example/a.png'))->toThrow(InvalidImage::class);
});

it('refuses image URLs when images.custom.urls is off, and still accepts inline images', function (): void {
    $policy = ImagePolicy::fromArray(['urls' => false]);
    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    expect(static fn (): EmojiImage => EmojiImage::from('https://example.com/a.png', $policy))->toThrow(InvalidImage::class, 'images.custom.urls')
        ->and(static fn (): EmojiImage => EmojiImage::from('/emoji/a.png', $policy))->toThrow(InvalidImage::class, 'images.custom.urls')
        ->and(EmojiImage::from($png, $policy)->src)->toStartWith('data:image/png')
        ->and(ImagePolicy::fromArray([])->allowUrls)->toBeTrue();
});

it('reads numeric strings in the image limits, as env() returns them', function (): void {
    $policy = ImagePolicy::fromArray(['max_bytes' => '1000', 'max_dimension' => '64', 'max_svg_elements' => '500']);

    expect([$policy->maxBytes, $policy->maxDimension, $policy->maxSvgElements])->toBe([1000, 64, 500]);
});

it('reads numeric strings in the input and policy limits too', function (): void {
    $emojis = Emojis::create(['input' => ['max_bytes' => '2048'], 'policy' => ['max_emojis' => '3']]);

    expect($emojis->options()->maxInputBytes)->toBe(2048)
        ->and($emojis->options()->policy->maxEmojis)->toBe(3)
        ->and(Emojis::create(['input' => ['max_bytes' => 'lots']])->options()->maxInputBytes)->toBe(1_048_576);
});
