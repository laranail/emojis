<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Tests;

use Simtabi\Laranail\Emojis\Providers\EmojisServiceProvider;
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;

/** Base case for tests that need a booted Laravel application. Unit tests do not use it; see tests/Pest.php. */
abstract class TestCase extends IsolatedTestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [EmojisServiceProvider::class];
    }
}
