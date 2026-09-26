<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Console\Tools\Contracts\EmojiCatalogue;

/**
 * Lends this catalogue to laranail/console. Console discovers this class by name
 * (EmojisBridge::ADAPTER) — it cannot import it, since it is the package being depended on — so
 * installing laranail/emojis is all it takes for Console::emoji() to resolve every shortcode and for
 * console's width measurement to recognise emoji newer than its own table.
 *
 * Constructed by console with no arguments, so the instance is resolved per call: the application's
 * configured binding when this package's provider has registered one, a standalone instance otherwise.
 */
final class ConsoleEmojiCatalogue implements EmojiCatalogue
{
    private ?Emojis $standalone = null;

    public function __construct(private readonly ?Emojis $emojis = null) {}

    public function glyph(string $name, bool $unicode): ?string
    {
        return $this->emojis()->fromShortcode($name)?->render($unicode ? Mode::Emoji : Mode::Ascii);
    }

    public function names(): array
    {
        return array_map(strval(...), array_keys($this->emojis()->catalogue()->shortcodeIndex()));
    }

    public function toShortcodes(string $text): string
    {
        return $this->emojis()->text($text)->from(Mode::Unicode)->to(Mode::Ascii);
    }

    public function isEmoji(string $cluster): bool
    {
        return $this->emojis()->fromChar($cluster) instanceof Emoji;
    }

    private function emojis(): Emojis
    {
        if ($this->emojis instanceof Emojis) {
            return $this->emojis;
        }

        if (function_exists('app') && app()->bound(Emojis::class)) {
            /** @var Emojis */
            return app(Emojis::class);
        }

        return $this->standalone ??= Emojis::create();
    }
}
