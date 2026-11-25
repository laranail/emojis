<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Support;

/**
 * Which warnings a reporter has already passed on, so each distinct one is logged once per worker.
 *
 * Bounded per subject: a long-running worker can be asked for any number of distinct values (a
 * ?locale= per request), and remembering every one would grow memory and the log without limit. After
 * DISTINCT_PER_SUBJECT distinct contexts for one subject, further new ones for it are dropped, and
 * suppressedNow() says so once, so the reporter can log that rather than going quiet.
 *
 * @internal shared by the PSR-3 and Laravel reporters, so both de-duplicate alike
 */
final class WarningLedger
{
    public const int DISTINCT_PER_SUBJECT = 32;

    /** @var array<string, true> */
    private array $seen = [];

    /** @var array<string, int> */
    private array $perSubject = [];

    /** @var array<string, bool> subject => whether the suppression notice is still owed (true) or given (false) */
    private array $suppressed = [];

    /** @param array<string, mixed> $context */
    public function first(string $subject, array $context): bool
    {
        $key = Psr3FailureReporter::warningKey($subject, $context);

        if (isset($this->seen[$key])) {
            return false;
        }

        if (($this->perSubject[$subject] ?? 0) >= self::DISTINCT_PER_SUBJECT) {
            $this->suppressed[$subject] ??= true;

            return false;
        }

        $this->seen[$key] = true;
        $this->perSubject[$subject] = ($this->perSubject[$subject] ?? 0) + 1;

        return true;
    }

    /** True once per subject, right after the cap first drops a new warning for it. */
    public function suppressedNow(string $subject): bool
    {
        if (($this->suppressed[$subject] ?? false) !== true) {
            return false;
        }

        $this->suppressed[$subject] = false;

        return true;
    }
}
