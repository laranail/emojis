<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Contracts;

use Throwable;

/**
 * The seam through which Core reports failures, so it can follow the failure-handling standard without
 * depending on a framework. The Laravel layer implements it on package-tools' FailurePolicy and BootReport;
 * pure PHP uses Psr3FailureReporter.
 *
 * Classification is the caller's: degraded() is for failures where continuing leaves a safe, reduced
 * state; warn() is for a tolerated anomaly worth seeing before it becomes a failure. Critical failures are
 * thrown by the caller, never routed through here to be continued past.
 */
interface FailureReporter
{
    /**
     * @param array<string, scalar|null> $context identifiers only — never user input
     */
    public function degraded(string $operation, Throwable $cause, array $context = []): void;

    /**
     * @param array<string, scalar|null> $context identifiers only — never user input
     */
    public function warn(string $subject, array $context = []): void;

    /**
     * The degraded state recorded so far, queryable (standard rule 7).
     *
     * @return array<string, string> operation => cause class
     */
    public function degradations(): array;
}
