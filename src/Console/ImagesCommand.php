<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Console;

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Image\ImageStore;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Emojis\Core\Exceptions\ImageSetNotFound;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * `laranail::emojis.images install twemoji` — download a built-in image set into
 * public/vendor/laranail/emojis/images/<set>, every file checked against the hash this package ships, then
 * sanitised. `verify` compares the copy on disk with what was installed (exit 1 on any change), for a
 * deploy check. Set `images.source` to `local` to serve from it.
 */
final class ImagesCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::emojis.images
                            {action : install or verify}
                            {set? : twemoji, noto, openmoji or fluent (default: the configured set)}';

    protected $description = 'Install a verified local copy of an emoji image set, or verify an installed one';

    public function handle(Emojis $emojis, ImageStore $store): int
    {
        $set = (string) ($this->argument('set') ?? $emojis->options()->imageSet);

        try {
            return match ((string) $this->argument('action')) {
                'install' => $this->install($store, $set),
                'verify'  => $this->verify($store, $set),
                default   => $this->invalid(),
            };
        } catch (ImageSetNotFound $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function install(ImageStore $store, string $set): int
    {
        $bar = null;
        $result = $store->install($set, function (int $done, int $total) use (&$bar): void {
            $bar ??= $this->output->createProgressBar($total);
            $bar->setProgress($done);
        });
        $bar?->finish();
        $this->newLine();

        $this->info(sprintf('%s: %d written, %d already current, %d without a published image.', $set, $result['written'], $result['kept'], $result['missing']));

        if ($result['failed'] !== []) {
            $this->error(sprintf('%d files failed to download or did not match their pinned hash; nothing was written for them. Re-run to retry.', count($result['failed'])));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function verify(ImageStore $store, string $set): int
    {
        if (! $store->installed($set)) {
            $this->error("{$set} is not installed; run laranail::emojis.images install {$set}.");

            return self::FAILURE;
        }

        $result = $store->verify($set);
        $this->info(sprintf('%s: %d files match, %d changed, %d missing.', $set, $result['ok'], count($result['changed']), count($result['missing'])));

        if ($result['stale']) {
            $this->warn("{$set} was installed for another dataset version; re-run install.");
        }

        return $result['changed'] === [] && $result['missing'] === [] && ! $result['stale'] ? self::SUCCESS : self::FAILURE;
    }

    private function invalid(): int
    {
        $this->error('Action must be install or verify.');

        return self::FAILURE;
    }
}
