<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Console;

use ValueError;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Emojis\Core\Enums\EscapeFormat;
use Simtabi\Laranail\Emojis\Core\Exceptions\EmojisException;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * `laranail::emojis.convert "Ship it :) :rocket:" --to=emoji --from=shortcode,emoticon`
 *
 * Reads the text argument, or STDIN when it is "-", so it works in a pipe.
 */
final class ConvertCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::emojis.convert
                            {text : The text to convert, or - to read STDIN}
                            {--to=auto : emoji, text, unicode, ascii, emoticon, shortcode, image, html_entity, escaped, codepoint, name, auto}
                            {--from=unicode,shortcode : Comma-separated source forms to parse}
                            {--format=php : Escape format for --to=escaped: php, javascript, python, css}
                            {--locale= : Locale for --to=name}';

    protected $description = 'Convert emoji in text from one form to another';

    public function handle(Emojis $emojis): int
    {
        $text = (string) $this->argument('text');

        if ($text === '-') {
            $text = (string) stream_get_contents(STDIN);
        }

        try {
            $to = Mode::from((string) $this->option('to'));
            $from = array_map(static fn (string $m): Mode => Mode::from(trim($m)), array_filter(explode(',', (string) $this->option('from'))));
            $converter = $emojis->text($text)->from(...$from);
            $locale = $this->option('locale');
            $converter = is_string($locale) && $locale !== '' ? $converter->locale($locale) : $converter;

            $this->output->write($converter->to($to, EscapeFormat::from((string) $this->option('format'))));
            $this->output->writeln('');
        } catch (ValueError $e) {
            $this->error('Unknown mode or format: ' . $e->getMessage());

            return self::INVALID;
        } catch (EmojisException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
