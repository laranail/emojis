<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Contracts\ImageSet;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidCustomEmoji;
use Simtabi\Laranail\Emojis\Core\Render\ImageSets\TemplateImageSet;

it('converts HTML text runs only and keeps markup byte-identical', function (): void {
    $html = '<p class="x" title=":wave:">hi :wave: &amp; :)</p><code>:wave: :)</code><pre>:wave:</pre><script>":wave:"</script>';

    expect(emojis()->html($html)->withEmoticons()->toEmoji())
        ->toBe('<p class="x" title=":wave:">hi 👋 &amp; 🙂</p><code>:wave: :)</code><pre>:wave:</pre><script>":wave:"</script>');
});

it('escapes emoticons that contain HTML specials when writing into HTML', function (): void {
    expect(emojis()->html('<p>love ❤️</p>')->to(Mode::Emoticon))->toBe('<p>love &lt;3</p>');
});

it('matches emoticons on decoded text', function (): void {
    expect(emojis()->html('<p>&lt;3</p>')->withEmoticons()->toEmoji())->toBe('<p>❤️</p>');
});

it('escapes plain text around images', function (): void {
    $out = (string) emojis()->text('<script>alert(1)</script> 🚀')->toHtml();

    expect($out)->toStartWith('&lt;script&gt;alert(1)&lt;/script&gt; <img ')
        ->and($out)->not->toContain('<script>');
});

it('escapes the non-image output of toHtml', function (): void {
    expect((string) emojis()->text('<b>:wave:</b>')->toHtml(Mode::Emoji))->toBe('&lt;b&gt;👋&lt;/b&gt;');
});

it('round-trips its own image markup', function (): void {
    $html = emojis()->text('go 🚀')->to(Mode::Image);

    expect(emojis()->text($html)->from(Mode::Image)->toEmoji())->toBe('go 🚀');
});

it('falls back to the native character when a set has no image', function (): void {
    // Noto's 2D set ships no country flags.
    expect(emojis()->text('🇰🇪')->imageSet('noto')->to(Mode::Image))->toBe('🇰🇪')
        ->and(emojis()->get('🇰🇪')->imageUrl('noto'))->toBeNull()
        ->and(emojis()->get('🇰🇪')->imageUrl('twemoji'))->toEndWith('/1f1f0-1f1ea.svg');
});

it('applies each publisher filename rule', function (): void {
    $heartOnFire = emojis()->get('❤️‍🔥');
    $heart = emojis()->get('❤️');

    expect($heart->imageUrl('twemoji'))->toEndWith('/2764.svg')
        ->and($heartOnFire->imageUrl('twemoji'))->toEndWith('/2764-fe0f-200d-1f525.svg')
        ->and($heartOnFire->imageUrl('noto'))->toEndWith('/emoji_u2764_200d_1f525.svg')
        ->and($heart->imageUrl('openmoji'))->toEndWith('/2764.svg')
        ->and(emojis()->get('👍🏽')->imageUrl('fluent'))->toEndWith('/Thumbs%20up/Medium/Color/thumbs_up_color_medium.svg')
        ->and(emojis()->get('👍')->imageUrl('fluent'))->toEndWith('/Thumbs%20up/Default/Color/thumbs_up_color_default.svg');
});

it('pins every CDN URL to a version', function (string $set): void {
    expect((string) emojis()->get('😀')->imageUrl($set))->not->toContain('@latest');
})->with(['twemoji', 'noto', 'openmoji', 'fluent']);

it('lets a consumer register and self-host image sets', function (): void {
    $emojis = Emojis::create(['image_base_urls' => ['twemoji' => 'https://cdn.example.com/tw']]);
    $emojis->addImageSet(new TemplateImageSet('mine', 'https://img.example.com/{hex_lower}.png'));

    expect($emojis->get('😀')->imageUrl())->toBe('https://cdn.example.com/tw/1f600.svg')
        ->and($emojis->get('😀')->imageUrl('mine'))->toBe('https://img.example.com/1f600.png');
});

it('renders custom emoji and refuses unsafe URLs', function (): void {
    $emojis = Emojis::create();
    $emojis->addCustom('laravel', 'https://example.com/laravel.svg', aliases: ['lara']);

    expect($emojis->text('hi :laravel: :lara:')->toImages())->toContain('src="https://example.com/laravel.svg"')
        ->and($emojis->text('hi :laravel:')->toEmoji())->toBe('hi :laravel:');

    $emojis->addCustom('evil', 'javascript:alert(1)');
})->throws(InvalidCustomEmoji::class);

it('refuses a custom name that shadows a Unicode shortcode', function (): void {
    Emojis::create()->addCustom('rocket', 'https://example.com/r.svg');
})->throws(InvalidCustomEmoji::class);

it('freezes registrations', function (): void {
    $emojis = Emojis::create()->freeze();

    $emojis->addImageSet(new class implements ImageSet
    {
        public function name(): string
        {
            return 'late';
        }

        public function url(Emoji $emoji): ?string
        {
            return null;
        }

        public function licence(): string
        {
            return 'none';
        }
    });
})->throws(InvalidCustomEmoji::class);
