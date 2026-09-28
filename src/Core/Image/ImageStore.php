<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Image;

use Closure;
use RuntimeException;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidImage;
use Simtabi\Laranail\Emojis\Core\Exceptions\ImageSetNotFound;

/**
 * A local copy of a built-in image set, so pages load emoji from the application's own origin rather than
 * a CDN. Nothing is bundled with the package; install() downloads the set once.
 *
 * Each file is trusted only after it proves itself: its SHA-256 must match the hash shipped in the dataset
 * for the pinned version (so a compromised or changed CDN file is refused), it is then sanitised, and it is
 * written atomically below `<root>/<set>/`, never outside it. A manifest records the hash of what was
 * written, so verify() detects any later change on disk.
 *
 * The fetcher is injected: it receives a batch of URLs and returns url => body (null on failure), which
 * lets the Laravel command pool requests and a test supply fixtures without a network.
 */
final readonly class ImageStore
{
    public const string MANIFEST = '.laranail-emojis.json';

    /**
     * @param Closure(list<string>): array<string, ?string> $fetch
     * @param positive-int $batch
     */
    public function __construct(
        private Emojis $emojis,
        private string $root,
        private Closure $fetch,
        private int $batch = 16,
    ) {}

    /** The URL path a local copy is served from, relative to the web root. */
    public static function publicPath(string $set): string
    {
        return 'vendor/laranail/emojis/images/' . $set;
    }

    /**
     * @param (Closure(int $done, int $total): void)|null $progress
     *
     * @return array{written: int, kept: int, failed: list<string>, missing: int}
     *
     * @throws ImageSetNotFound when the set has no hash manifest (a registered set, or JoyPixels, whose
     *                          licence does not allow redistribution)
     */
    public function install(string $set, ?Closure $progress = null): array
    {
        $hashes = $this->hashes($set);
        $cdn = $this->emojis->images()->upstream($set);
        $manifest = $this->manifest($set);
        $written = [];
        $failed = [];
        $kept = 0;
        $missing = 0;
        $jobs = [];

        foreach ($this->emojis->catalogue()->all() as $emoji) {
            $path = $cdn->path($emoji);
            $url = $cdn->url($emoji);
            $hash = $hashes[$emoji->hexcode] ?? null;

            if ($path === null || $url === null || $hash === null) {
                $missing += $path !== null ? 1 : 0;

                continue;
            }

            $target = $this->target($set, $path);

            if (isset($manifest['files'][$path]) && is_file($target) && $this->digest((string) file_get_contents($target)) === $manifest['files'][$path]) {
                $written[$path] = $manifest['files'][$path];
                $kept++;

                continue;
            }

            $jobs[] = [$url, $path, $target, $hash];
        }

        $total = count($jobs);

        foreach (array_chunk($jobs, $this->batch) as $index => $chunk) {
            $bodies = ($this->fetch)(array_column($chunk, 0));

            foreach ($chunk as [$url, $path, $target, $hash]) {
                $body = $bodies[$url] ?? null;

                if ($body === null || $this->digest($body) !== $hash) {
                    $failed[] = $path;

                    continue;
                }

                try {
                    $clean = SvgSanitizer::sanitize($body, 100_000, trusted: true);
                } catch (InvalidImage) {
                    $failed[] = $path;

                    continue;
                }

                $this->write($target, $clean);
                $written[$path] = $this->digest($clean);
            }

            if ($progress instanceof Closure) {
                $progress(min($total, ($index + 1) * $this->batch), $total);
            }
        }

        ksort($written, SORT_STRING);
        $this->write($this->root . '/' . $set . '/' . self::MANIFEST, json_encode([
            'set'     => $set,
            'version' => $this->emojis->dataset()->imageVersions()[$set] ?? '',
            'dataset' => $this->emojis->datasetVersion(),
            'files'   => $written,
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");

        return ['written' => count($written) - $kept, 'kept' => $kept, 'failed' => $failed, 'missing' => $missing];
    }

    /**
     * Compare what is on disk with what install() wrote.
     *
     * @return array{ok: int, changed: list<string>, missing: list<string>, stale: bool}
     */
    public function verify(string $set): array
    {
        $manifest = $this->manifest($set);
        $changed = [];
        $missing = [];
        $ok = 0;

        foreach ($manifest['files'] as $path => $hash) {
            $target = $this->target($set, $path);

            if (! is_file($target)) {
                $missing[] = $path;
            } elseif ($this->digest((string) file_get_contents($target)) !== $hash) {
                $changed[] = $path;
            } else {
                $ok++;
            }
        }

        $version = $this->emojis->dataset()->imageVersions()[$set] ?? '';

        return ['ok' => $ok, 'changed' => $changed, 'missing' => $missing, 'stale' => $manifest['version'] !== $version];
    }

    public function installed(string $set): bool
    {
        return is_file($this->root . '/' . $set . '/' . self::MANIFEST);
    }

    /** @return array<array-key, string> */
    private function hashes(string $set): array
    {
        $hashes = $this->emojis->dataset()->imageHashes($set);

        if ($hashes === []) {
            throw ImageSetNotFound::notInstallable($set);
        }

        return $hashes;
    }

    /** @return array{version: string, files: array<string, string>} */
    private function manifest(string $set): array
    {
        $file = $this->root . '/' . $set . '/' . self::MANIFEST;
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $files = [];

        foreach (is_array($data) && is_array($data['files'] ?? null) ? $data['files'] : [] as $path => $hash) {
            if (is_string($hash)) {
                $files[(string) $path] = $hash;
            }
        }

        return ['version' => is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : '', 'files' => $files];
    }

    /** The file for a URL path below the set's directory; refuses anything that would leave it. */
    private function target(string $set, string $path): string
    {
        $relative = rawurldecode($path);

        if (preg_match('#(^|/)\.\.?(/|$)|\\\\|\x00|^/#', $relative) === 1 || preg_match('/^[a-z0-9-]+$/', $set) !== 1) {
            throw new RuntimeException('Refusing an image path outside the image directory.');
        }

        return $this->root . '/' . $set . '/' . $relative;
    }

    private function write(string $target, string $contents): void
    {
        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create the image directory.');
        }

        $temporary = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $target)) {
            @unlink($temporary);

            throw new RuntimeException('Cannot write an image file.');
        }
    }

    private function digest(string $bytes): string
    {
        return substr(hash('sha256', $bytes), 0, 32);
    }
}
