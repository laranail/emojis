<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Console;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Emojis\Core\Enums\EscapeFormat;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/** `laranail::emojis.show wave` — every form of one emoji. */
final class ShowCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::emojis.show
                            {emoji : A character, shortcode, hexcode, slug or emoticon}
                            {--locale= : Show names in this locale}
                            {--json : Print as JSON}';

    protected $description = 'Show every form of one emoji: shortcodes, text, ASCII, escapes, images';

    public function handle(Emojis $emojis): int
    {
        $emoji = $emojis->find((string) $this->argument('emoji'));

        if (! $emoji instanceof Emoji) {
            $this->error('No emoji matches that key.');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($emoji, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $locale = $this->option('locale');
        $locale = is_string($locale) ? $locale : null;

        $this->table(['Form', 'Value'], [
            ['Emoji', $emoji->render(Mode::Auto)],
            ['Name', $emoji->name($locale)],
            ['Hexcode', $emoji->hexcode],
            ['Group', $emoji->group->label() . ' / ' . $emoji->subgroup->label()],
            ['Version', 'Emoji ' . $emoji->version->value],
            ['Shortcodes', implode(' ', $emoji->allShortcodes())],
            ['Emoticons', implode(' ', $emoji->emoticons())],
            ['Keywords', implode(', ', $emoji->keywords($locale))],
            ['Text', $emoji->hasTextPresentation ? 'yes (VS15)' : 'no'],
            ['ASCII', $emoji->toAscii()],
            ['Codepoints', $emoji->toCodepoints()],
            ['HTML entity', $emoji->toHtmlEntity()],
            ['PHP escape', $emoji->escaped(EscapeFormat::Php)],
            ['JS escape', $emoji->escaped(EscapeFormat::JavaScript)],
            ['Skin tones', $emoji->base()->supportsSkinTones() ? $emoji->skinToneVariants()->render(Mode::Auto, ' ') : 'none'],
            ['Image', (string) $emoji->imageUrl()],
        ]);

        return self::SUCCESS;
    }
}
