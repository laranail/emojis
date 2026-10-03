<?php

declare(strict_types=1);

use Livewire\Livewire;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Blade;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Tests\TestCase;
use Illuminate\Contracts\Translation\Translator;
use Simtabi\Laranail\Emojis\Laravel\View\PickerConfig;
use Simtabi\Laranail\Emojis\Core\Picker\PayloadBuilder;
use Simtabi\Laranail\Emojis\Laravel\Doctor\PickerCheck;
use Simtabi\Laranail\Emojis\Laravel\View\PickerPayloads;
use Simtabi\Laranail\Emojis\Laravel\Livewire\EmojiPicker;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorStatus;

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
        ->not->toContain('data-laranail-emoji-columns=') // corrected to 8, the module's own default
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

it('passes the popover options, and drops a placement the script would not know', function (): void {
    $html = Blade::render('<x-laranail-emojis::picker placement="top-end" :offset="12" :arrow="false" :sheet-breakpoint="0" />');

    expect($html)->toContain('data-laranail-emoji-placement="top-end"')
        ->toContain('data-laranail-emoji-offset="12"')
        ->toContain('data-laranail-emoji-arrow="false"')
        ->toContain('data-laranail-emoji-sheet-breakpoint="0"')
        ->and(Blade::render('<x-laranail-emojis::picker placement="sideways" />'))->not->toContain('data-laranail-emoji-placement');
});

it('takes its defaults from laranail.emojis.picker, which attributes override', function (): void {
    TestCase::$bootConfig = ['laranail.emojis.picker' => [
        'columns'  => 10, 'placement' => 'top-end', 'arrow' => false, 'trigger' => '😺', 'theme' => 'dark',
        'features' => ['search' => false, 'preview' => false], 'sort' => 'bogus',
    ]];
    $this->refreshApplication();

    $html = Blade::render('<x-laranail-emojis::picker />');
    $override = Blade::render('<x-laranail-emojis::picker :columns="6" placement="bottom" :arrow="true" theme="auto" :features="[\'search\' => true]" />');

    expect($html)->toContain('data-laranail-emoji-columns="10"')
        ->toContain('data-laranail-emoji-placement="top-end"')
        ->toContain('data-laranail-emoji-arrow="false"')
        ->toContain('data-laranail-emoji-trigger="😺"')
        ->toContain('data-theme="dark"')
        ->toContain('data-laranail-emoji-features="{&quot;search&quot;:false,&quot;preview&quot;:false}"')
        ->not->toContain('data-laranail-emoji-sort=')
        ->and($override)->toContain('data-laranail-emoji-columns="6"')
        ->toContain('data-laranail-emoji-placement="bottom"')
        ->not->toContain('data-laranail-emoji-arrow=')
        ->not->toContain('data-theme=')
        ->toContain('data-laranail-emoji-features="{&quot;preview&quot;:false}"');
});

it('adds kaomoji and symbols to the payload only when their tabs are on, and drops custom emoji when that tab is off', function (): void {
    expect(payloadIn(Blade::render('<x-laranail-emojis::picker />')))->not->toHaveKeys(['kaomoji', 'symbols']);

    TestCase::$bootConfig = [
        'laranail.emojis.picker.features' => ['kaomoji' => true, 'symbols' => true, 'custom' => false],
        'laranail.emojis.extend.custom'   => ['partyparrot' => ['image' => PNG]],
    ];
    $this->refreshApplication();

    $payload = payloadIn(Blade::render('<x-laranail-emojis::picker />'));

    expect($payload['kaomoji'][0]['items'][0])->toHaveKeys(['text', 'name'])
        ->and(array_sum(array_map(static fn (array $g): int => count($g['items']), $payload['kaomoji'])))->toBeGreaterThan(2000)
        ->and($payload['symbols'][0]['slug'])->toBe('popular')
        ->and($payload['symbols'][0]['items'][0])->toHaveKeys(['char', 'name'])
        ->and($payload['custom'])->toBe([]);
});

it('reads the config defensively: a wrong type takes the built-in default', function (): void {
    $config = PickerConfig::fromArray(['columns' => '12', 'offset' => 999, 'placement' => 'sideways', 'features' => ['search' => 'no', 'kaomoji' => true], 'delivery' => 'carrier-pigeon']);

    expect([$config->columns, $config->offset, $config->placement, $config->delivery])->toBe([8, 64, 'auto', 'auto'])
        ->and($config->enabled('search'))->toBeTrue()
        ->and($config->enabled('kaomoji'))->toBeTrue()
        ->and($config->enabled('symbols'))->toBeFalse();
});

it('embeds the payload when delivery is inline even with the API on, and fetches when it is api or auto', function (): void {
    TestCase::$bootConfig = ['laranail.emojis.api.enabled' => true, 'laranail.emojis.picker.delivery' => 'inline'];
    $this->refreshApplication();
    expect(Blade::render('<x-laranail-emojis::picker />'))->toContain('application/json')->not->toContain('data-laranail-emoji-source');

    TestCase::$bootConfig = ['laranail.emojis.api.enabled' => true, 'laranail.emojis.picker.delivery' => 'auto'];
    $this->refreshApplication();
    expect(Blade::render('<x-laranail-emojis::picker />'))->toContain('data-laranail-emoji-source')->not->toContain('application/json');

    TestCase::$bootConfig = ['laranail.emojis.picker.delivery' => 'api'];
    $this->refreshApplication();
    // api without the API turned on falls back to embedding, so the picker still loads.
    expect(Blade::render('<x-laranail-emojis::picker />'))->toContain('application/json');
});

it('has a doctor check that warns about a large embedded payload and about api delivery without the API', function (): void {
    $check = static fn (array $picker, bool $api): PickerCheck => new PickerCheck(app(PickerPayloads::class), PickerConfig::fromArray($picker), new Illuminate\Config\Repository(['laranail' => ['emojis' => ['api' => ['enabled' => $api]]]]));

    $inline = $check([], false)->run();
    $api = $check(['delivery' => 'api'], false)->run();
    $fetched = $check([], true)->run();

    expect($inline->status)->toBe(DoctorStatus::Warn)
        ->and($inline->message)->toContain('embedded in every page')
        ->and($api->status)->toBe(DoctorStatus::Warn)
        ->and($api->message)->toContain('the API is off')
        ->and($fetched->status)->toBe(DoctorStatus::Pass)
        ->and($fetched->message)->toContain('from the API');
});

it('carries the image set by default, none for native rendering, and every switchable set for the switcher', function (): void {
    $default = payloadIn(Blade::render('<x-laranail-emojis::picker />'));

    expect($default['images']['set'])->toBe('twemoji')
        ->and($default['images']['rule'])->toBe('twemoji')
        ->and($default)->not->toHaveKey('imageSets');

    TestCase::$bootConfig = ['laranail.emojis.picker.render' => 'native'];
    $this->refreshApplication();
    $native = Blade::render('<x-laranail-emojis::picker />');

    expect(payloadIn($native))->not->toHaveKey('images')
        ->and($native)->toContain('data-laranail-emoji-render="native"');

    TestCase::$bootConfig = ['laranail.emojis.picker' => ['image_set' => 'noto', 'features' => ['set_switcher' => true]]];
    $this->refreshApplication();
    $switch = Blade::render('<x-laranail-emojis::picker />');

    expect(payloadIn($switch)['images']['set'])->toBe('noto')
        ->and(array_column(payloadIn($switch)['imageSets'], 'set'))->toBe(['noto', 'twemoji', 'openmoji'])
        ->and(html_entity_decode($switch))->toContain('data-laranail-emoji-features="{"setSwitcher":true}"');
});

it('warns when the picker falls back to an image set with no coverage data', function (): void {
    TestCase::$bootConfig = ['laranail.emojis.picker.image_set' => 'joypixels', 'laranail.emojis.api.enabled' => true];
    $this->refreshApplication();

    $result = app(PickerCheck::class)->run();

    expect($result->status)->toBe(DoctorStatus::Warn)
        ->and($result->message)->toContain('JoyPixels');
});
