<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel;

use Throwable;
use Simtabi\Laranail\Package\Tools\Enums\BootCriticality;
use Simtabi\Laranail\Emojis\Core\Contracts\FailureReporter;
use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * Routes Core's degradable failures through the family's failure runner, so they reach the application's
 * exception handler and monitoring (standard rule 3) and show up in BootReport — the degraded-state surface
 * the doctor and the CI boot-health gate read (rule 7).
 *
 * One report per operation per worker (rule 9): a broken locale shard would otherwise be reported on every
 * conversion.
 */
final class LaravelFailureReporter implements FailureReporter
{
    private const string PREFIX = 'laranail/emojis:';

    /** @var array<string, string> */
    private array $degraded = [];

    public function __construct(private readonly ?BootReport $report = null) {}

    public function degraded(string $operation, Throwable $cause, array $context = []): void
    {
        if (isset($this->degraded[$operation])) {
            return;
        }

        $this->degraded[$operation] = $cause::class;
        FailurePolicy::handle($cause, self::PREFIX . $operation, BootCriticality::Degradable);
    }

    public function warn(string $subject, array $context = []): void
    {
        FailurePolicy::warn(self::PREFIX . $subject, $context);
    }

    public function degradations(): array
    {
        $out = $this->degraded;

        foreach ($this->report?->degraded() ?? [] as $name => $detail) {
            if (str_starts_with($name, self::PREFIX)) {
                $out[substr($name, strlen(self::PREFIX))] = (string) ($detail['cause_type'] ?? 'unknown');
            }
        }

        return $out;
    }
}
