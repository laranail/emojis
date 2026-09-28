<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Tests\TestCase;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;

/*
|--------------------------------------------------------------------------
| Test case bindings
|--------------------------------------------------------------------------
|
| Only Feature tests boot Laravel. Unit and Datasets tests exercise src/Core with no container, which is the
| runtime proof that Core stayed framework-free (deptrac and .dev/tools/core-isolation.php are the static and
| autoloader proofs).
|
*/

uses(TestCase::class)->in('Feature');

/**
 * One framework-free instance for the Unit and Datasets suites. The terminal probe is pinned so Mode::Auto
 * does not depend on the machine running the tests.
 */
function emojis(): Emojis
{
    static $instance = null;

    return $instance ??= Emojis::create(terminal: new EnvTerminalProbe(override: true));
}
