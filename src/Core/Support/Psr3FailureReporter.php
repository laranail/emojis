<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Support;

use Throwable;

use function error_log;

use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Emojis\Core\Contracts\FailureReporter;

/**
 * The framework-free FailureReporter: logs through any PSR-3 logger and keeps the degraded state in memory.
 *
 * Reporting is guarded (standard rule 8): a logger that throws falls back to error_log() and never turns
 * a degradable failure into a crash. Repeated reports of the same operation are logged once per instance
 * (rule 9), since a long-running worker would otherwise log the same broken shard on every call.
 */
final class Psr3FailureReporter implements FailureReporter
{
    /** @var array<string, string> */
    private array $degraded = [];

    /** @var array<string, true> */
    private array $warned = [];

    public function __construct(private readonly LoggerInterface $logger = new NullLogger) {}

    public function degraded(string $operation, Throwable $cause, array $context = []): void
    {
        $first = ! isset($this->degraded[$operation]);
        $this->degraded[$operation] = $cause::class;

        if ($first) {
            $this->log('error', "laranail/emojis degraded [{$operation}]", ['operation' => $operation, 'decision' => 'degraded-and-continued', 'cause_type' => $cause::class, 'cause' => $cause->getMessage(), ...$context], $cause);
        }
    }

    public function warn(string $subject, array $context = []): void
    {
        if (isset($this->warned[$subject])) {
            return;
        }

        $this->warned[$subject] = true;
        $this->log('warning', "laranail/emojis tolerated anomaly [{$subject}]", ['subject' => $subject, 'decision' => 'tolerated', ...$context]);
    }

    public function degradations(): array
    {
        return $this->degraded;
    }

    /** @param array<string, mixed> $context */
    private function log(string $level, string $message, array $context, ?Throwable $cause = null): void
    {
        try {
            $this->logger->log($level, $message, $cause instanceof Throwable ? [...$context, 'exception' => $cause] : $context);
        } catch (Throwable $reportingFailure) {
            error_log($message . ' (logger failed: ' . $reportingFailure::class . ')');
        }
    }
}
