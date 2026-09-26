<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Text;

use JsonSerializable;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmoji;

/**
 * One emoji found by extract(). Offsets are UTF-8 byte offsets into the input, the unit substr() and
 * preg_* use. JavaScript counts UTF-16 code units instead; utf16Offset() converts for a JS consumer.
 */
final readonly class EmojiMatch implements JsonSerializable
{
    public function __construct(
        public int $offset,
        public int $length,
        public string $text,
        public Mode $source,
        public ?Emoji $emoji,
        public ?CustomEmoji $custom,
        private string $subject,
    ) {}

    /** True when the dataset does not know this sequence (a future or vendor-specific emoji). */
    public function isUnknown(): bool
    {
        return ! $this->emoji instanceof Emoji && ! $this->custom instanceof CustomEmoji;
    }

    public function utf16Offset(): int
    {
        return intdiv(strlen(mb_convert_encoding(substr($this->subject, 0, $this->offset), 'UTF-16LE', 'UTF-8')), 2);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'offset'       => $this->offset,
            'length'       => $this->length,
            'utf16_offset' => $this->utf16Offset(),
            'text'         => $this->text,
            'source'       => $this->source->value,
            'hexcode'      => $this->emoji?->hexcode,
            'custom'       => $this->custom?->name,
        ];
    }
}
