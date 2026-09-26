<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Console;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/** `laranail::emojis.search cat --locale=fr` — ranked search over names, keywords and shortcodes. */
final class SearchCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::emojis.search
                            {term : Words to look for}
                            {--locale= : Search this locale\'s names and keywords}
                            {--limit=20 : How many results}';

    protected $description = 'Search the emoji catalogue by name, keyword or shortcode';

    public function handle(Emojis $emojis): int
    {
        $locale = $this->option('locale');
        $results = $emojis->search((string) $this->argument('term'), is_string($locale) ? $locale : null, max(1, (int) $this->option('limit')));

        if ($results->isEmpty()) {
            $this->warn('No emoji matched.');

            return self::FAILURE;
        }

        $this->table(['', 'Name', 'Shortcode', 'Hexcode'], $results->map(static fn (Emoji $e): array => [
            $e->render(Mode::Auto),
            $e->name(is_string($locale) ? $locale : null),
            $e->render(Mode::Shortcode),
            $e->hexcode,
        ]));

        return self::SUCCESS;
    }
}
