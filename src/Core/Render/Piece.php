<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Render;

/** One rendered emoji: its text, and whether that text is HTML (and must not be escaped again). */
final readonly class Piece
{
    public function __construct(
        public string $text,
        public bool $html,
    ) {}
}
