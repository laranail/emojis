<?php

declare(strict_types=1);

use Livewire\Livewire;
use Illuminate\Support\Facades\Blade;
use Simtabi\Laranail\Emojis\Tests\TestCase;
use Simtabi\Laranail\Emojis\Laravel\Livewire\EmojiPicker;

const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

afterEach(function (): void {
    TestCase::$bootConfig = [];
});

/** The JSON data block a page carries, decoded, or null when there is none. */
function payloadIn(string $html): ?array
{
    return preg_match('/<script type="application\/json" id="[^"]+">(.*?)<\/script>/s', $html, $m) === 1 ? json_decode($m[1], true, flags: JSON_THROW_ON_ERROR) : null;
}

it('inlines or links the picker stylesheet beside the emoji one', function (): void {
    $inline = Blade::render('<x-laranail-emojis::styles picker />');
    $link = Blade::render('<x-laranail-emojis::styles picker link />');

    expect($inline)->toContain('.laranail-emoji-picker-panel')->toContain('.laranail-emoji-image')
        ->and(Blade::render('<x-laranail-emojis::styles />'))->not->toContain('.laranail-emoji-picker')
        ->and($link)->toContain('vendor/laranail/emojis/css/emojis.css')->toContain('vendor/laranail/emojis/css/picker.css');
});

it('inlines the picker module with a nonce, or links the published file', function (): void {
    $inline = Blade::render('<x-laranail-emojis::scripts nonce="abc" />');

    expect($inline)->toStartWith('<script type="module" nonce="abc">')
        ->toContain('as Picker,')
        ->and(substr_count(strtolower($inline), '</script'))->toBe(1)
        ->and(Blade::render('<x-laranail-emojis::scripts link />'))->toContain('src="http://localhost/vendor/laranail/emojis/js/picker.js"');
});

it('renders the mount point with its options, and embeds the payload once per locale', function (): void {
    $html = Blade::render('<x-laranail-emojis::picker target="#message" inline :categories="[\'recent\', \'flags\']" :max-recent="12" sort="newest" :columns="9" :close-on-select="false" />'
        . '<x-laranail-emojis::picker target="#other" />');

    expect(substr_count($html, '<script type="application/json"'))->toBe(1)
        ->and(substr_count($html, 'data-laranail-emoji-payload="laranail-emoji-picker-data-en"'))->toBe(2)
        ->and($html)->toContain('data-laranail-emoji-target="#message"')
        ->toContain('data-laranail-emoji-categories="recent,flags"')
        ->toContain('data-laranail-emoji-max-recent="12"')
        ->toContain('data-laranail-emoji-sort="newest"')
        ->toContain('data-laranail-emoji-columns="9"')
        ->toContain('data-laranail-emoji-close-on-select="false"')
        ->toContain('data-laranail-emoji-inline')
        ->toContain('<noscript>The emoji picker needs JavaScript.')
        ->and(payloadIn($html)['groups'][0]['slug'])->toBe('smileys_and_emotion');
});

it('passes its interface strings from the translations', function (): void {
    preg_match('/data-laranail-emoji-strings="([^"]+)"/', Blade::render('<x-laranail-emojis::picker />'), $m);
    $strings = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true, flags: JSON_THROW_ON_ERROR);

    expect($strings)->toMatchArray(['search' => 'Search emoji', 'noResults' => 'No emoji found'])
        ->and($strings['tones'])->toHaveCount(6);
});

it('cannot be broken out of by a custom emoji label, since the JSON escapes markup', function (): void {
    TestCase::$bootConfig = ['laranail.emojis.extend.custom' => ['partyparrot' => ['image' => PNG, 'label' => '</script><script>alert(1)</script>']]];
    $this->refreshApplication();

    $html = Blade::render('<x-laranail-emojis::picker />');

    expect(substr_count(strtolower($html), '</script'))->toBe(1)
        ->and(payloadIn($html)['custom'][0]['label'])->toBe('</script><script>alert(1)</script>');
});

it('offers only what the policy permits', function (): void {
    TestCase::$bootConfig = ['laranail.emojis.policy' => ['deny_groups' => ['flags'], 'allow_custom' => false]];
    $this->refreshApplication();

    $payload = payloadIn(Blade::render('<x-laranail-emojis::picker />'));

    expect(array_column($payload['groups'], 'slug'))->not->toContain('flags')
        ->and($payload['custom'])->toBe([]);
});

it('loads from the API instead of embedding when the API is enabled', function (): void {
    TestCase::$bootConfig = ['laranail.emojis.api.enabled' => true];
    $this->refreshApplication();

    $html = Blade::render('<x-laranail-emojis::picker locale="fr" />');

    expect($html)->toContain('data-laranail-emoji-source="http://localhost/laranail/emojis/api/v1/picker"')
        ->toContain('data-laranail-emoji-locale="fr"')
        ->not->toContain('application/json');
});

it('registers the Livewire component under a vendor-scoped name, bound to the parent with wire:model', function (): void {
    expect(app('livewire.finder')->resolveClassComponentClassName('laranail-emojis.picker'))->toBe(EmojiPicker::class);

    Livewire::test(EmojiPicker::class, ['value' => 'hi', 'locale' => 'fr', 'placeholder' => 'Say hi'])
        ->assertSet('value', 'hi')
        ->assertSeeHtml('wire:model="value"')
        ->assertSeeHtml('placeholder="Say hi"')
        ->assertSeeHtml('data-laranail-emoji-picker')
        ->assertSeeHtml('data-laranail-emoji-locale="fr"')
        ->set('value', 'hi 👋')
        ->assertSet('value', 'hi 👋');
});
