<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Contracts\EmojisFluent;

/**
 * The fluent helper. Namespaced, so it can never collide with another package's global emoji():
 *
 *     use function Simtabi\Laranail\Emojis\emoji;
 *
 *     emoji('wave');                        // Emoji 👋
 *     emoji()->text('hi :wave:')->toEmoji();  // "hi 👋"
 *
 * The return type is an interface, so IDEs and static analysis see its methods without magic: lookup, search,
 * conversion and symbols. It is deliberately smaller than Emojis, so implementing it stays cheap; for the
 * rest (sanitize(), has(), all(), collection(), …) type against Emojis or use the facade, whose docblock
 * lists every public method. Resolves the container's instance, so config, custom emoji and test swaps all
 * apply; outside Laravel, call Emojis::create() instead.
 */
function emoji(?string $key = null): Emoji|EmojisFluent
{
    /** @var EmojisFluent $emojis */
    $emojis = app(Emojis::class);

    return $key === null ? $emojis : $emojis->get($key);
}
