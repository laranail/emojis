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
 * The return type is an interface, so IDEs and static analysis see every method without magic. Resolves the
 * container's instance, so config, custom emoji and test swaps all apply; outside Laravel, call
 * Emojis::create() instead.
 */
function emoji(?string $key = null): Emoji|EmojisFluent
{
    /** @var EmojisFluent $emojis */
    $emojis = app(Emojis::class);

    return $key === null ? $emojis : $emojis->get($key);
}
