<?php

declare(strict_types=1);

use Livewire\Livewire;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Blade;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Tests\TestCase;
use Illuminate\Contracts\Translation\Translator;
use Simtabi\Laranail\Emojis\Core\Picker\PayloadBuilder;
use Simtabi\Laranail\Emojis\Laravel\View\PickerPayloads;
use Simtabi\Laranail\Emojis\Laravel\Livewire\EmojiPicker;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;

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

    // Relative: fetched from whichever host serves the page, not APP_URL's.
    expect($html)->toContain('data-laranail-emoji-source="/laranail/emojis/api/v1/picker"')
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

it('targets the Livewire textarea with a selector that holds for ids starting with a digit', function (): void {
    $html = Livewire::test(EmojiPicker::class)->html();

    preg_match('/<textarea id="([^"]+)"/', $html, $id);
    preg_match('/data-laranail-emoji-target="([^"]+)"/', $html, $target);

    // "#3abc-input" is not a valid CSS selector, and Livewire ids start with a digit one time in six.
    expect(html_entity_decode($target[1], ENT_QUOTES | ENT_HTML5))->toBe("[id='{$id[1]}']")
        ->and($target[1])->not->toStartWith('#');
});

it('marks the mount point wire:ignore, so a Livewire morph does not strip the mounted picker', function (): void {
    expect(Blade::render('<x-laranail-emojis::picker />'))->toMatch('/<div data-laranail-emoji-picker wire:ignore /');
});

it('carries the configured shortcode delimiters, so a custom emoji is inserted as a code that reads back', function (): void {
    TestCase::$bootConfig = ['laranail.emojis.shortcodes.delimiters' => ['{{', '}}']];
    $this->refreshApplication();

    expect(payloadIn(Blade::render('<x-laranail-emojis::picker />'))['delimiters'])->toBe(['{{', '}}']);
});

it('passes other attributes through to the mount point, without letting them override its options', function (): void {
    $html = Blade::render('<x-laranail-emojis::picker class="reply-picker" id="reply" data-laranail-emoji-locale="xx" data-testid="p" />');

    expect($html)->toContain('class="reply-picker"')
        ->toContain('id="reply"')
        ->toContain('data-testid="p"')
        ->toContain('data-laranail-emoji-locale="en"')
        ->not->toContain('data-laranail-emoji-locale="xx"');
});

it('takes a version cap and a trigger, and corrects out-of-range options', function (): void {
    $html = Blade::render('<x-laranail-emojis::picker max-version="13.0" trigger="😺" :tone="9" :columns="0" sort="bogus" recent-order="bogus" :max-recent="-3" />');

    expect($html)->toContain('data-laranail-emoji-max-version="13.0"')
        ->toContain('data-laranail-emoji-trigger="😺"')
        ->toContain('data-laranail-emoji-tone="0"')
        ->toContain('data-laranail-emoji-columns="8"')
        ->toContain('data-laranail-emoji-max-recent="0"')
        ->not->toContain('data-laranail-emoji-sort=')
        ->not->toContain('data-laranail-emoji-recent-order=');
});

it('translates group names from the picker translations', function (): void {
    app('translator')->addLines(['picker.groups.flags' => 'Drapeaux', 'picker.results_one' => '1 résultat'], 'fr', 'laranail/emojis');

    $html = Blade::render('<x-laranail-emojis::picker locale="fr" />');
    $groups = array_column(payloadIn($html)['groups'], 'label', 'slug');

    expect($groups['flags'])->toBe('Drapeaux')
        ->and($groups['smileys_and_emotion'])->toBe('Smileys & Emotion')
        ->and(html_entity_decode($html))->toContain('"resultsOne":"1 résultat"');
});

it('ships picker translations for every dataset locale, each complete and shaped like English', function (): void {
    $root = dirname(__DIR__, 2);
    $locales = array_map(static fn (string $f): string => basename($f, '.php'), glob($root . '/database/generated/locales/*.php') ?: []);
    $english = require $root . '/resources/lang/en/picker.php';
    $shape = static fn (array $lines): array => [array_keys($lines), array_keys($lines['groups']), count($lines['tones'])];

    expect(count($locales))->toBeGreaterThanOrEqual(20);

    foreach ($locales as $locale) {
        $file = $root . "/resources/lang/{$locale}/picker.php";

        expect($file)->toBeFile();

        $lines = require $file;

        expect($shape($lines))->toBe($shape($english), $locale)
            ->and($lines['results'])->toContain('{count}');
    }
});

it('serves translated interface strings, and English where a locale lacks one', function (): void {
    preg_match('/data-laranail-emoji-strings="([^"]+)"/', Blade::render('<x-laranail-emojis::picker locale="sw" />'), $m);
    $strings = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true, flags: JSON_THROW_ON_ERROR);

    expect($strings['search'])->toBe('Tafuta emoji')
        ->and($strings['tones'])->toHaveCount(6)
        ->and(array_column(payloadIn(Blade::render('<x-laranail-emojis::picker locale="sw" />'))['groups'] ?? [], 'label'))->each->toBeString();

    // A group the translator cannot find comes back as its key, a string: that must read as no strings.
    $translator = Mockery::mock(Translator::class);
    $translator->allows('get')->andReturn('laranail/emojis::picker');
    $payloads = new PickerPayloads(app(Emojis::class), app(PayloadBuilder::class), $translator);

    expect($payloads->strings('en'))->toBe([])
        ->and($payloads->noScript('en'))->toBe('')
        ->and($payloads->payload('en')->groups[0]['label'])->toBe('Smileys & Emotion');
});

it('writes the data block from the layout, and pickers after it do not repeat it', function (): void {
    $html = Blade::render('<x-laranail-emojis::picker-data /><x-laranail-emojis::picker /><x-laranail-emojis::picker />');

    expect(substr_count($html, '<script type="application/json"'))->toBe(1)
        ->and($html)->toStartWith('<script type="application/json" id="laranail-emoji-picker-data-en">');

    // Unconditional: written again even after a picker claimed the locale (its markup may have been a
    // cached fragment that never reached this page).
    expect(substr_count(Blade::render('<x-laranail-emojis::picker /><x-laranail-emojis::picker-data />'), 'application/json'))->toBe(1);
});

it('caches the payload, under a key that changes with what the payload depends on', function (): void {
    $cache = new Repository(new ArrayStore(serializesValues: true));
    $payloads = static fn (Emojis $emojis): PickerPayloads => new PickerPayloads($emojis, new PayloadBuilder($emojis), app('translator'), $cache);
    $plain = Emojis::create(terminal: new EnvTerminalProbe(override: true));
    $first = $payloads($plain)->payload('en');

    expect($cache->getStore()->all())->toHaveCount(1)
        ->and($payloads($plain)->payload('en'))->toEqual($first);

    $custom = Emojis::create(terminal: new EnvTerminalProbe(override: true))->addCustom('partyparrot', PNG);
    $after = $payloads($custom)->payload('en');

    expect($cache->getStore()->all())->toHaveCount(2)
        ->and(array_column($after->custom, 'name'))->toBe(['partyparrot']);
});

it('gives the Livewire textarea a name and an accessible name, and locks the locale', function (): void {
    Livewire::test(EmojiPicker::class, ['name' => 'body', 'placeholder' => 'Say hi'])
        ->assertSeeHtml('name="body"')
        ->assertSeeHtml('aria-label="Say hi"');

    expect(fn () => Livewire::test(EmojiPicker::class, ['locale' => 'fr'])->set('locale', 'de'))->toThrow(Exception::class);
});
