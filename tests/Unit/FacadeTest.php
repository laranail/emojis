<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Facades\Emojis as EmojisFacade;

// The facade's @method lines are what an IDE and PHPStan offer for Emojis::…(); a public method missing
// there is invisible to anyone using the facade, and 21 were before this guard.
it('documents every public Emojis method on the facade, with its parameters in order', function (): void {
    $doc = (string) new ReflectionClass(EmojisFacade::class)->getDocComment();
    preg_match_all('/@method\s+static\s+[^\s<]+(?:<[^>]*>)?\s+(\w+)\(([^)]*)\)/', $doc, $lines, PREG_SET_ORDER);
    $documented = [];

    foreach ($lines as [, $name, $params]) {
        preg_match_all('/\$(\w+)/', $params, $names);
        $documented[$name] = $names[1];
    }

    $inspected = 0;

    foreach (new ReflectionClass(Emojis::class)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        // Static factories and macro plumbing are not forwarded instance calls; @internal is not public API.
        if ($method->isStatic() || $method->isConstructor() || str_starts_with($method->name, '__') || str_contains((string) $method->getDocComment(), '@internal')) {
            continue;
        }

        $inspected++;
        $expected = array_map(static fn (ReflectionParameter $p): string => $p->name, $method->getParameters());

        expect(array_key_exists($method->name, $documented))->toBeTrue("Emojis::{$method->name}() has no @method line on the facade")
            ->and($documented[$method->name] ?? null)->toBe($expected, "the facade's {$method->name}() parameters drifted");
    }

    expect($inspected)->toBeGreaterThan(40)
        ->and(array_diff(array_keys($documented), array_map(static fn (ReflectionMethod $m): string => $m->name, new ReflectionClass(Emojis::class)->getMethods())))->toBe([]);
});
