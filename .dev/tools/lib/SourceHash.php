<?php

declare(strict_types=1);

/**
 * The SHA-256 a source is pinned by.
 *
 * Plain files hash as downloaded. JSON from an API (GitHub's git-trees endpoint, jsDelivr's listing
 * endpoint) is hashed in a canonical form — decoded and re-encoded — because those services do not return
 * stable bytes: measured 2026-09-28, the same request for the same commit-pinned tree came back
 * pretty-printed (425,091 bytes) and minified (345,320 bytes) twenty minutes apart, with identical data. A
 * raw-byte pin on that fails at random; the canonical pin still fails the moment the data changes.
 */
final class SourceHash
{
    private const array API_HOSTS = ['api.github.com', 'data.jsdelivr.com'];

    public static function of(string $url, string $file): string
    {
        $bytes = (string) file_get_contents($file);

        if (! in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::API_HOSTS, true)) {
            return hash('sha256', $bytes);
        }

        $data = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }
}
