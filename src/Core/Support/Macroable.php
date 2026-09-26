<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Support;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use BadMethodCallException;

/**
 * Add methods at runtime without subclassing — the Illuminate Macroable contract, reimplemented so Core
 * stays framework-free.
 *
 * Macros are process-global, exactly like Laravel's: registering one in a long-running worker affects
 * every later request in that worker. Register them at boot, and call flushMacros() between tests.
 */
trait Macroable
{
    /** @var array<class-string, array<string, callable>> */
    private static array $macros = [];

    /** @param array<array-key, mixed> $parameters */
    public function __call(string $method, array $parameters): mixed
    {
        $macro = self::$macros[static::class][$method] ?? throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));

        if ($macro instanceof Closure) {
            $macro = Closure::bind($macro, $this, static::class) ?? $macro;
        }

        return $macro(...$parameters);
    }

    /** @param array<array-key, mixed> $parameters */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        $macro = self::$macros[static::class][$method] ?? throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));

        if ($macro instanceof Closure) {
            $macro = Closure::bind($macro, null, static::class) ?? $macro;
        }

        return $macro(...$parameters);
    }

    public static function macro(string $name, callable $macro): void
    {
        self::$macros[static::class][$name] = $macro;
    }

    public static function mixin(object $mixin, bool $replace = true): void
    {
        foreach (new ReflectionClass($mixin)->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            if ($replace || ! static::hasMacro($method->name)) {
                $closure = $method->invoke($mixin);

                if ($closure instanceof Closure) {
                    static::macro($method->name, $closure);
                }
            }
        }
    }

    public static function hasMacro(string $name): bool
    {
        return isset(self::$macros[static::class][$name]);
    }

    public static function flushMacros(): void
    {
        unset(self::$macros[static::class]);
    }
}
