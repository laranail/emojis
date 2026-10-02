<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds `Vary: Accept-Language`. Without ?locale= a response follows the application locale, which a locale
 * middleware commonly sets from that header, so a shared cache must not hand one language to another.
 */
final class VaryByLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $response->setVary('Accept-Language', false);

        return $response;
    }
}
