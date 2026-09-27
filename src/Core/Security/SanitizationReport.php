<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Security;

use JsonSerializable;

/**
 * What a sanitizer pass removed, counted by kind. Safe to log: it holds counts, never the input or the
 * removed characters (which may be exactly the payload an attacker wanted written somewhere).
 */
final readonly class SanitizationReport implements JsonSerializable
{
    /** @param array<string, int> $counts Threat value => characters or emoji removed */
    public function __construct(private array $counts = []) {}

    public function isClean(): bool
    {
        return $this->counts === [];
    }

    public function count(?Threat $threat = null): int
    {
        return $threat instanceof Threat ? $this->counts[$threat->value] ?? 0 : (array_sum($this->counts));
    }

    public function has(Threat $threat): bool
    {
        return $this->count($threat) > 0;
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return $this->counts;
    }

    /** @return array<string, int> */
    public function jsonSerialize(): array
    {
        return $this->counts;
    }
}
