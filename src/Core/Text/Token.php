<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Text;

use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\Qualification;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmoji;

/**
 * One emoji occurrence found in text: where it is (byte offset and length), what form it was written in,
 * and what it resolves to — a catalogue hexcode, a custom emoji, or neither (a pictograph the dataset does
 * not know, found only by the sanitising pass).
 */
final readonly class Token
{
    public function __construct(
        public int $offset,
        public int $length,
        public Mode $source,
        public ?string $hexcode = null,
        public ?CustomEmoji $custom = null,
        public ?Qualification $qualification = null,
    ) {}

    public function end(): int
    {
        return $this->offset + $this->length;
    }

    public function isKnown(): bool
    {
        return $this->hexcode !== null || $this->custom instanceof CustomEmoji;
    }

    public function text(string $subject): string
    {
        return substr($subject, $this->offset, $this->length);
    }
}
