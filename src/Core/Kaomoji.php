<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core;

use Stringable;
use JsonSerializable;

/**
 * A text face — ASCII like ":-)" or not, like "¯\_(ツ)_/¯" — from the kaomoji catalogue. Japanese faces carry
 * their tags and kana readings, so they can be found the way a Japanese IME dictionary finds them.
 */
final readonly class Kaomoji implements JsonSerializable, Stringable
{
    public function __construct(
        public string $value,
        public string $group,
        public string $description,
        public bool $isAscii,
        /** @var list<string> */
        public array $tags = [],
        /** @var list<string> */
        public array $readings = [],
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    /** @return array{value: string, group: string, description: string, ascii: bool, tags: list<string>, readings: list<string>} */
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'group' => $this->group, 'description' => $this->description, 'ascii' => $this->isAscii, 'tags' => $this->tags, 'readings' => $this->readings];
    }
}
