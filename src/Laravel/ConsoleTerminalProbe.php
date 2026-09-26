<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel;

use Simtabi\Laranail\Console\Tools\Support\Capabilities;
use Simtabi\Laranail\Emojis\Core\Contracts\TerminalProbe;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;

/**
 * Inside Laravel, terminal detection defers to laranail/console, so one setting and one test fake
 * (Capabilities::fake()) govern every laranail command's output. Core's EnvTerminalProbe still has the last
 * word on the cases console treats as Unicode-capable but that cannot draw emoji (the Linux console,
 * TERM=dumb, the legacy Windows host),; an explicit LARANAIL_EMOJIS override wins over both.
 */
final readonly class ConsoleTerminalProbe implements TerminalProbe
{
    public function __construct(private EnvTerminalProbe $env = new EnvTerminalProbe) {}

    public function supportsEmoji(): bool
    {
        return $this->env->forced() ?? (Capabilities::detect()->supportsUnicode() && $this->env->supportsEmoji());
    }

    public function supportsFlags(): bool
    {
        return $this->supportsEmoji() && $this->env->supportsFlags();
    }
}
