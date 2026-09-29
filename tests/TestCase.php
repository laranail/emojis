<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Tests;

use Simtabi\Laranail\Emojis\Providers\EmojisServiceProvider;
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;

/** Base case for tests that need a booted Laravel application. Unit tests do not use it; see tests/Pest.php. */
abstract class TestCase extends IsolatedTestCase
{
    /**
     * Config a test needs in place before the provider boots, such as extend.* and input.disabled_emoticons,
     * which are registered at boot and frozen after it. Set it, call refreshApplication(), and reset it.
     *
     * @var array<string, mixed>
     */
    public static array $bootConfig = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach (self::$bootConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [EmojisServiceProvider::class];
    }
}
