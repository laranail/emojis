<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * What every API response gets, whatever the application's own middleware:
 *
 * - **API keys**, when `api.keys` lists any: a request must send one as `Authorization: Bearer <key>`, or it
 *   is a 401. Keys are compared in constant time, through their SHA-256, so neither content nor length leaks.
 *   A keyed response is `Cache-Control: private` and varies by `Authorization`, so a shared cache never hands
 *   it to a caller without the key.
 * - **Cross-origin reads**, for the origins `api.allowed_origins` lists (or `*`): the API serves another
 *   front end without the application editing `config/cors.php`. Only simple GETs are served this way; an
 *   origin is echoed only when listed, and never with credentials.
 * - **Hardening headers** for a JSON body: `nosniff`, a CSP that lets nothing load or frame it, and no referrer.
 */
final readonly class GuardApi
{
    public function __construct(private ConfigRepository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $keys = $this->keys();

        if ($keys !== [] && ! $this->authorised($request->bearerToken(), $keys)) {
            return $this->harden(new JsonResponse(['message' => 'A valid API key is required.'], 401, ['WWW-Authenticate' => 'Bearer']), $request);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($keys !== []) {
            $response->headers->removeCacheControlDirective('public');
            $response->setPrivate();
            $response->setVary('Authorization', false);
        }

        return $this->harden($response, $request);
    }

    /** @param list<string> $keys */
    private function authorised(?string $token, array $keys): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $given = hash('sha256', $token);
        $match = false;

        // Every key is compared, so the time taken does not say which one, or whether an early one, matched.
        foreach ($keys as $key) {
            $match = hash_equals(hash('sha256', $key), $given) || $match;
        }

        return $match;
    }

    private function harden(Response $response, Request $request): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $response->headers->set('Referrer-Policy', 'no-referrer');

        $origins = $this->origins();
        $origin = $request->headers->get('Origin');

        if (in_array('*', $origins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } elseif (is_string($origin) && in_array($origin, $origins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        if ($origins !== []) {
            // The answer differs by origin unless it is *, so a shared cache keys on it.
            if (! in_array('*', $origins, true)) {
                $response->setVary('Origin', false);
            }

            $response->headers->set('Access-Control-Expose-Headers', 'ETag');
        }

        return $response;
    }

    /** @return list<string> */
    private function keys(): array
    {
        return $this->strings($this->config->get('laranail.emojis.api.keys', []));
    }

    /** @return list<string> */
    private function origins(): array
    {
        return array_map(static fn (string $origin): string => rtrim($origin, '/'), $this->strings($this->config->get('laranail.emojis.api.allowed_origins', [])));
    }

    /**
     * A list from config: an array, or a comma-separated string (as an env value arrives); blanks dropped.
     *
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        $items = is_string($value) ? explode(',', $value) : (is_array($value) ? $value : []);

        return array_values(array_filter(array_map(static fn (mixed $item): string => is_string($item) ? trim($item) : '', $items), static fn (string $item): bool => $item !== ''));
    }
}
