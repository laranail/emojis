<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Contracts;

/** Decides what Mode::Auto resolves to on the current output. */
interface TerminalProbe
{
    public function supportsEmoji(): bool;

    /** Whether flag sequences render as flags (legacy Windows consoles show two letters). */
    public function supportsFlags(): bool;
}
