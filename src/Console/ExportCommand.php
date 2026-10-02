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
 *
 * `--with=tags,emoticons,kaomoji,symbols` (or `--with=all`) adds those catalogues as top-level sections.
 * They are opt-in because symbols alone are about a megabyte; without --with the document is unchanged.
 */
final class ExportCommand extends Command
{
    use SupportsNamespacedNames;

    public const int SCHEMA_VERSION = 1;

    /** The optional sections --with accepts, in the order they are written. */
    public const array SECTIONS = ['tags', 'emoticons', 'kaomoji', 'symbols'];

    protected $signature = 'laranail::emojis.export
                            {path? : Write here instead of STDOUT}
                            {--locale= : Include names and keywords in this locale}
                            {--variants : Include skin-tone variants}
                            {--with= : Also export these, comma-separated: tags, emoticons, kaomoji, symbols, or all}';

    protected $description = 'Export the emoji catalogue as JSON (schema version ' . self::SCHEMA_VERSION . ')';

    public function handle(Emojis $emojis): int
    {
        $locale = $this->option('locale');
        $locale = is_string($locale) && $locale !== '' ? $locale : null;
        $query = $emojis->query()->withSkinToneVariants((bool) $this->option('variants'));
        $with = $this->sections();

        if ($with === null) {
            return self::FAILURE;
        }

        $list = $query->get()->map(static fn (Emoji $e): array => [
            ...$e->jsonSerialize(),
            'name'       => $e->name($locale),
            'keywords'   => $e->keywords($locale),
            'shortcodes' => $e->allShortcodes(),
            'emoticons'  => $e->emoticons(),
            'skins'      => $e->skins,
        ]);
        $document = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'dataset'       => $emojis->datasetVersion(),
            'locale'        => $emojis->locales()->resolve($locale),
            'emojis'        => $list,
        ];
        $sections = [
            'tags'      => static fn (): array => ['groups' => $emojis->tags()->groups(), 'tags' => $emojis->tags()->all()],
            'emoticons' => static fn (): array => array_map(static fn (Emoji $e): string => $e->hexcode, $emojis->emoticons(risky: true)),
            'kaomoji'   => static fn (): array => ['groups' => $emojis->kaomojiGroups(), 'kaomoji' => $emojis->kaomoji()],
            'symbols'   => static fn (): array => ['groups' => array_combine($emojis->symbols()->groups(), array_map($emojis->symbols()->characters(...), $emojis->symbols()->groups())), 'symbols' => $emojis->symbols()->all()],
        ];

        foreach ($with as $section) {
            $document[$section] = $sections[$section]();
        }

        $json = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = $this->argument('path');

        if (is_string($path) && $path !== '') {
            if (file_put_contents($path, $json) === false) {
                $this->error("Could not write {$path}.");

                return self::FAILURE;
            }

            $this->info(sprintf('Wrote %d emoji to %s.', count($list), $path));

            return self::SUCCESS;
        }

        $this->output->writeln($json);

        return self::SUCCESS;
    }

    /** @return list<string>|null the requested sections, in SECTIONS order; null after reporting an unknown one */
    private function sections(): ?array
    {
        $option = $this->option('with');
        $asked = is_string($option) && $option !== '' ? array_map(trim(...), explode(',', strtolower($option))) : [];
        $asked = in_array('all', $asked, true) ? self::SECTIONS : $asked;
        $unknown = array_diff($asked, self::SECTIONS);

        if ($unknown !== []) {
            $this->error('Unknown --with section: ' . implode(', ', $unknown) . '. Use ' . implode(', ', self::SECTIONS) . ' or all.');

            return null;
        }

        return array_values(array_intersect(self::SECTIONS, $asked));
    }
}
