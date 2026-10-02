<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Contracts;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiId;
use Simtabi\Laranail\Emojis\Core\Catalogue\Query;
use Simtabi\Laranail\Emojis\Core\Symbols\Symbols;
use Simtabi\Laranail\Emojis\Core\Text\TextConverter;
use Simtabi\Laranail\Emojis\Core\Catalogue\EmojiCollection;

/**
 * The typed face of the package for helpers and IDEs: the `emoji()` helper and the facade resolve to an
 * implementation of this, so completion and static analysis work without macros or magic methods.
 *
 * A subset of Emojis on purpose: widening it breaks every class that implements it, so new methods land on
 * Emojis (and the facade's docblock) first.
 */
interface EmojisFluent
{
    public function get(Emoji|EmojiId|string $key): Emoji;

    public function find(Emoji|EmojiId|string $key): ?Emoji;

    public function flag(string $region): Emoji;

    public function query(): Query;

    public function search(string $term, ?string $locale = null, int $limit = 24): EmojiCollection;

    public function text(string $text): TextConverter;

    public function html(string $html): TextConverter;

    public function convert(string $text, Mode $to, Mode ...$from): string;

    public function strip(string $text): string;

    public function contains(string $text): bool;

    /** Special characters that are not emoji: arrows, currency, maths, letters, punctuation, hieroglyphs. */
    public function symbols(): Symbols;
}
