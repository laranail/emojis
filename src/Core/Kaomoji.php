<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core;

use Stringable;
use JsonSerializable;

/** A text face — ASCII like ":-)" or not, like "¯\_(ツ)_/¯" — from the kaomoji catalogue. */
final readonly class Kaomoji implements JsonSerializable, Stringable
{
    public function __construct(
        public string $value,
        public string $group,
        public string $description,
        public bool $isAscii,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    /** @return array{value: string, group: string, description: string, ascii: bool} */
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'group' => $this->group, 'description' => $this->description, 'ascii' => $this->isAscii];
    }
}
