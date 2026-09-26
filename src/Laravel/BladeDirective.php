<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel;

use Illuminate\Support\HtmlString;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;

/**
 * What `@laranailEmojis(...)` compiles to. The directive emits a call to this class rather than inline code,
 * so the compiled view holds only a class name — view:cache stays valid across deploys, and the container
 * (config, custom emoji, test swaps) is consulted when the view renders, not when it compiles.
 *
 * In a view: `@laranailEmojis($comment->body)` renders images with the text escaped;
 * `@laranailEmojis($comment->body, 'emoji')` renders native emoji with the text escaped.
 */
final class BladeDirective
{
    public static function compile(string $expression): string
    {
        return trim($expression) === '' ? '' : '<?php echo \\' . self::class . '::render(' . $expression . '); ?>';
    }

    public static function render(?string $text, string $mode = 'image'): HtmlString
    {
        if ($text === null || $text === '') {
            return new HtmlString('');
        }

        return new HtmlString((string) app(Emojis::class)->text($text)->toHtml(Mode::from($mode)));
    }
}
