<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Console;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * `laranail::emojis.export public/emojis.json --locale=fr` — the catalogue as JSON for a JavaScript picker or
 * another language. The document carries a schemaVersion; fields are only ever added within a version.
 */
final class ExportCommand extends Command
{
    use SupportsNamespacedNames;

    public const int SCHEMA_VERSION = 1;

    protected $signature = 'laranail::emojis.export
                            {path? : Write here instead of STDOUT}
                            {--locale= : Include names and keywords in this locale}
                            {--variants : Include skin-tone variants}';

    protected $description = 'Export the emoji catalogue as JSON (schema version ' . self::SCHEMA_VERSION . ')';

    public function handle(Emojis $emojis): int
    {
        $locale = $this->option('locale');
        $locale = is_string($locale) && $locale !== '' ? $locale : null;
        $query = $emojis->query()->withSkinToneVariants((bool) $this->option('variants'));

        $document = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'dataset'       => $emojis->datasetVersion(),
            'locale'        => $emojis->locales()->resolve($locale),
            'emojis'        => $query->get()->map(static fn (Emoji $e): array => [
                ...$e->jsonSerialize(),
                'name'       => $e->name($locale),
                'keywords'   => $e->keywords($locale),
                'shortcodes' => $e->allShortcodes(),
                'emoticons'  => $e->emoticons(),
                'skins'      => $e->skins,
            ]),
        ];

        $json = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = $this->argument('path');

        if (is_string($path) && $path !== '') {
            if (file_put_contents($path, $json) === false) {
                $this->error("Could not write {$path}.");

                return self::FAILURE;
            }

            $this->info(sprintf('Wrote %d emoji to %s.', count($document['emojis']), $path));

            return self::SUCCESS;
        }

        $this->output->writeln($json);

        return self::SUCCESS;
    }
}
