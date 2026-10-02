<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Console;

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * `laranail::emojis.sanitize "text"` or `… sanitize - < file` — prints the cleaned text on STDOUT and the
 * report (counts by kind, never content) on STDERR, so it composes in a pipe. With --check it changes and
 * prints nothing, and exits 1 when sanitising would remove anything — a hidden character or something the
 * configured emoji policy disallows — for use as a CI or pre-commit gate over text files.
 */
final class SanitizeCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::emojis.sanitize
                            {text : The text, or - to read STDIN}
                            {--check : Print nothing; exit 1 when sanitising would remove anything}';

    protected $description = 'Remove smuggled, invisible and disallowed characters and emoji from text';

    public function handle(Emojis $emojis): int
    {
        $text = (string) $this->argument('text');
        $text = $text === '-' ? (string) stream_get_contents(STDIN) : $text;
        $result = $emojis->sanitize($text)->run();

        if ($this->option('check')) {
            return $result->report->isClean() ? self::SUCCESS : self::FAILURE;
        }

        $this->output->write($result->text);

        foreach ($result->report->toArray() as $threat => $count) {
            fwrite(STDERR, sprintf("removed %d × %s\n", $count, $threat));
        }

        return self::SUCCESS;
    }
}
