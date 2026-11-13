<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Symfony\Component\Yaml\Yaml;
use Simtabi\Laranail\Emojis\Tests\TestCase;
use Illuminate\Support\Facades\Route as Router;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;

const API = '/laranail/emojis/api/v1';

/** Boots the application with the API on (plus any extra config), since routes are registered at boot. */
function bootApi(array $config = []): void
{
    TestCase::$bootConfig = ['laranail.emojis.api.enabled' => true, ...$config];
    test()->refreshApplication();
}

afterEach(function (): void {
    TestCase::$bootConfig = [];
});

it('registers no route at all while the API is off, which is the default', function (): void {
    expect(collect(Router::getRoutes()->getRoutes())->filter(static fn (Route $r): bool => str_starts_with((string) $r->getName(), 'laranail.emojis.api.')))->toHaveCount(0);
    $this->getJson(API . '/emojis')->assertNotFound();
});

it('registers every route under a vendor-scoped name, with a controller, so route:cache keeps it', function (): void {
    bootApi();
    $routes = collect(Router::getRoutes()->getRoutes())->filter(static fn (Route $r): bool => str_starts_with((string) $r->getName(), 'laranail.emojis.api.'));

    expect($routes)->toHaveCount(9);

    foreach ($routes as $route) {
        expect($route->methods())->toBe(['GET', 'HEAD'])
            ->and($route->getActionName())->not->toBe('Closure')
            ->and($route->uri())->toStartWith('laranail/emojis/api/v1');
    }
});

it('describes the dataset, counts, locales, groups and links', function (): void {
    bootApi();

    $this->getJson(API)->assertOk()
        ->assertJsonPath('data.counts.tags', 78)
        ->assertJsonPath('data.groups.0.slug', 'smileys_and_emotion')
        ->assertJsonStructure(['data' => ['dataset', 'locale', 'locales', 'counts' => ['emojis', 'symbols', 'kaomoji', 'emoticons'], 'links' => ['emojis', 'picker']]]);
});

it('lists emoji with filters, a page and the total, in the requested locale', function (): void {
    bootApi();

    $this->getJson(API . '/emojis?q=fus%C3%A9e&locale=fr&limit=1')->assertOk()
        ->assertJsonPath('data.0.emoji', '🚀')
        ->assertJsonPath('data.0.name', 'fusée')
        ->assertJsonPath('data.0.english_name', 'rocket')
        ->assertJsonPath('meta.limit', 1);

    $page = $this->getJson(API . '/emojis?group=flags&limit=10&offset=5')->assertOk();
    expect($page->json('meta.total'))->toBeGreaterThan(250)
        ->and($page->json('data'))->toHaveCount(10);
});

it('refuses out-of-bounds input with 422 and never echoes it', function (string $query, string $field): void {
    bootApi();

    $response = $this->getJson(API . '/emojis?' . $query)->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($response->getContent())->not->toContain('<script>');
})->with([
    'limit too large'  => ['limit=1000', 'limit'],
    'unknown group'    => ['group=%3Cscript%3E', 'group'],
    'malformed locale' => ['locale=%3Cscript%3E', 'locale'],
    'term too long'    => ['q=' . str_repeat('a', 101), 'q'],
]);

it('resolves an unshipped locale to a fallback rather than refusing it', function (): void {
    bootApi();

    $this->getJson(API . '/emojis/rocket?locale=pt-BR')->assertOk()->assertJsonPath('data.hexcode', '1F680');
});

it('shows one emoji by character, hexcode or shortcode, percent-encoded sequences included', function (string $key, string $hexcode): void {
    bootApi();

    $this->getJson(API . '/emojis/' . rawurlencode($key))->assertOk()->assertJsonPath('data.hexcode', $hexcode);
})->with([
    'character'   => ['🚀', '1F680'],
    'hexcode'     => ['1F680', '1F680'],
    'shortcode'   => ['rocket', '1F680'],
    'zwj family'  => ['👨‍👩‍👧', '1F468-200D-1F469-200D-1F467'],
    'flag'        => ['🇰🇪', '1F1F0-1F1EA'],
    'keycap hash' => ['#️⃣', '0023-FE0F-20E3'],
    'keycap star' => ['*️⃣', '002A-FE0F-20E3'],
]);

it('answers 404 for an unknown key, symbol group or kaomoji group', function (string $path): void {
    bootApi();

    $this->getJson(API . $path)->assertNotFound()->assertJsonStructure(['message']);
})->with(['/emojis/no-such-emoji', '/symbols/no-such-group', '/kaomoji?group=no_such_group']);

it('never serves an emoji the configured policy denies', function (): void {
    bootApi(['laranail.emojis.policy' => ['deny' => ['1F680'], 'deny_groups' => ['flags']]]);

    $this->getJson(API . '/emojis/rocket')->assertNotFound();
    expect($this->getJson(API . '/emojis?group=flags')->json('meta.total'))->toBe(0)
        ->and(collect($this->getJson(API . '/picker')->json('data.groups'))->pluck('slug'))->not->toContain('flags');
});

it('serves the picker payload, grouped, localized and without components', function (): void {
    bootApi();
    $payload = $this->getJson(API . '/picker?locale=fr')->assertOk()->json();

    expect($payload['data']['locale'])->toBe('fr')
        ->and(collect($payload['data']['groups'])->pluck('slug'))->not->toContain('component')
        ->and($payload['meta']['count'])->toBeGreaterThan(1800)
        ->and($payload['data']['groups'][0]['emoji'][0])->toHaveKeys(['emoji', 'hexcode', 'name', 'shortcode', 'keywords', 'version', 'skins']);
});

it('serves symbols, kaomoji, emoticons and tags', function (): void {
    bootApi();

    expect(collect($this->getJson(API . '/symbols')->assertOk()->json('data'))->pluck('group'))->toContain('currency');
    $this->getJson(API . '/symbols/currency')->assertOk()->assertJsonPath('meta.group', 'currency');
    $this->getJson(API . '/kaomoji?ascii=1')->assertOk()->assertJsonStructure(['data', 'meta' => ['groups', 'count']]);
    $this->getJson(API . '/emoticons')->assertOk()->assertJsonPath('data.:)', '1F642');
    $this->getJson(API . '/tags')->assertOk()->assertJsonPath('data.0.text', '[OK]');
});

it('answers a repeat request with 304 through its ETag, and varies by language', function (): void {
    bootApi();
    $first = $this->get(API . '/emojis/rocket')->assertOk()->assertHeader('Vary');
    $etag = (string) $first->headers->get('ETag');

    expect($etag)->not->toBe('')
        ->and((string) $first->headers->get('Vary'))->toContain('Accept-Language')
        ->and((string) $first->headers->get('Cache-Control'))->toContain('max-age=3600');

    $this->get(API . '/emojis/rocket', ['If-None-Match' => $etag])->assertStatus(304);
    $this->get(API . '/emojis/rocket?locale=fr', ['If-None-Match' => $etag])->assertOk();
});

it('sends no CORS header to another origin unless the application allows the prefix', function (): void {
    bootApi();

    $this->get(API . '/emojis/rocket', ['Origin' => 'https://evil.example'])->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('takes its prefix, version and middleware from config', function (): void {
    bootApi(['laranail.emojis.api.prefix' => 'emoji-data', 'laranail.emojis.api.version' => 'v9', 'laranail.emojis.api.cache_headers' => '']);

    $response = $this->get('/emoji-data/v9/emojis/rocket')->assertOk();
    expect($response->headers->get('ETag'))->toBeNull();
});

it('sends CORS headers once the application adds the prefix to config/cors.php, as documented', function (): void {
    bootApi(['cors.paths' => ['laranail/emojis/api/*'], 'cors.allowed_origins' => ['https://app.example']]);

    $this->get(API . '/emojis/rocket', ['Origin' => 'https://app.example'])->assertOk()->assertHeader('Access-Control-Allow-Origin', 'https://app.example');
});

it('documents every registered route in the shipped OpenAPI file, and nothing that is not registered', function (): void {
    bootApi();
    $spec = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/openapi/emojis-api.yaml');
    preg_match_all('/^  (\/[^:]*):$/m', $spec, $m);
    $routes = collect(Router::getRoutes()->getRoutes())
        ->filter(static fn (Route $r): bool => str_starts_with((string) $r->getName(), 'laranail.emojis.api.'))
        ->map(static fn (Route $r): string => '/' . ltrim(substr($r->uri(), strlen('laranail/emojis/api/v1')), '/'))
        ->sort()->values()->all();

    expect($routes)->toHaveCount(9)
        ->and(collect($m[1])->sort()->values()->all())->toBe($routes);
});

it('serves the picker payload in the shape the OpenAPI file describes', function (): void {
    bootApi();
    // Parsing the whole file is itself a check: unquoted commas in flow mappings once made it invalid YAML.
    $spec = Yaml::parseFile(dirname(__DIR__, 2) . '/resources/openapi/emojis-api.yaml');
    $schemas = $spec['components']['schemas'];
    $payload = $this->getJson(API . '/picker')->assertOk()->json();
    $required = static fn (string $schema): array => $schemas[$schema]['required'];
    $first = $payload['data']['groups'][0]['emoji'][0];
    $handshake = collect($payload['data']['groups'])->flatMap(static fn (array $g): array => $g['emoji'])->firstWhere('hexcode', '1F91D');

    expect(array_keys($payload))->toEqualCanonicalizing(['data', 'meta'])
        ->and(array_diff($required('PickerPayload'), array_keys($payload['data'])))->toBe([])
        ->and(array_diff(array_keys($payload['data']), array_keys($schemas['PickerPayload']['properties'])))->toBe([])
        ->and(array_diff($required('PickerGroup'), array_keys($payload['data']['groups'][0])))->toBe([])
        ->and(array_diff($required('PickerEmoji'), array_keys($first)))->toBe([])
        ->and(array_diff(array_keys($handshake), array_keys($schemas['PickerEmoji']['properties'])))->toBe([])
        ->and($spec['paths']['/picker']['get']['responses'])->toHaveKeys(['200', '422', '429']);
});

it('hardens every response, an error included, and allows no other origin by default', function (): void {
    bootApi();

    foreach ([API . '/emojis?limit=1', API . '/emojis/not-an-emoji-at-all'] as $url) {
        $response = $this->getJson($url, ['Origin' => 'https://elsewhere.example']);

        expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; frame-ancestors 'none'")
            ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
            ->and($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    }
});

it('lets the listed origins read it from the browser, and only those', function (): void {
    bootApi(['laranail.emojis.api.allowed_origins' => 'https://app.example, https://admin.example/']);

    $allowed = $this->getJson(API . '/tags', ['Origin' => 'https://admin.example']);
    $other = $this->getJson(API . '/tags', ['Origin' => 'https://evil.example']);

    expect($allowed->headers->get('Access-Control-Allow-Origin'))->toBe('https://admin.example')
        ->and($allowed->headers->get('Access-Control-Expose-Headers'))->toBe('ETag')
        ->and(implode(', ', $allowed->getVary()))->toContain('Origin')
        ->and($allowed->headers->has('Access-Control-Allow-Credentials'))->toBeFalse()
        ->and($other->headers->has('Access-Control-Allow-Origin'))->toBeFalse();

    bootApi(['laranail.emojis.api.allowed_origins' => ['*']]);
    expect($this->getJson(API . '/tags', ['Origin' => 'https://any.example'])->headers->get('Access-Control-Allow-Origin'))->toBe('*');
});

it('requires one of the configured API keys, and keeps keyed responses out of shared caches', function (): void {
    $first = TestCredentials::secret('first');
    $second = TestCredentials::secret('second');

    expect($first)->not->toBe($second);
    bootApi(['laranail.emojis.api.keys' => "{$first}, {$second}"]);

    $this->getJson(API . '/tags')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer')->assertJson(['message' => 'A valid API key is required.']);
    $this->getJson(API . '/tags', ['Authorization' => 'Bearer ' . $first . 'x'])->assertUnauthorized();
    $this->getJson(API . '/tags', ['Authorization' => 'Basic ' . base64_encode($first)])->assertUnauthorized();

    $ok = $this->getJson(API . '/tags', ['Authorization' => 'Bearer ' . $second])->assertOk();

    expect($ok->headers->getCacheControlDirective('private'))->toBeTrue()
        ->and($ok->headers->hasCacheControlDirective('public'))->toBeFalse()
        ->and(implode(', ', $ok->getVary()))->toContain('Authorization')
        ->and($ok->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('leaves the API open and publicly cacheable while no key is configured', function (): void {
    bootApi(['laranail.emojis.api.keys' => '']);

    $response = $this->getJson(API . '/tags')->assertOk();

    expect($response->headers->hasCacheControlDirective('public'))->toBeTrue();
});
