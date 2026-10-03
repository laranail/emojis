<?php

declare(strict_types=1);

/**
 * The Authorization header for a download, when it goes to the GitHub API and a token is available.
 *
 * Two upstream listings (the Noto and Twemoji trees) come from api.github.com, which allows 60 unauthenticated
 * requests an hour per IP. CI runners share IPs, so an unauthenticated sync-check failed with HTTP 403 when the
 * shared quota was spent (laranail/emojis#37, 2026-10-03) — every retry from the same runner too. With the
 * workflow's GITHUB_TOKEN the limit is per repository and far higher.
 *
 * The token goes to https://api.github.com and nowhere else: not to a lookalike host, not over http, not to the
 * CDNs the other sources come from.
 */
final class GitHubAuth
{
    /** The header line, or null when the URL is not the GitHub API or there is no token. */
    public static function headerFor(string $url, ?string $token): ?string
    {
        $parts = parse_url($url);

        if ($token === null || $token === '' || ! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'api.github.com' || isset($parts['port']) || isset($parts['user'])) {
            return null;
        }

        return 'Authorization: Bearer ' . $token;
    }

    /**
     * curl arguments that send the header from a private temporary file, so the token never appears on a
     * command line (visible to `ps`) or in an error message. Call the returned cleanup when curl has run.
     *
     * @return array{0: string, 1: Closure(): void} arguments (with a leading space, or empty), cleanup
     */
    public static function curlArguments(string $url): array
    {
        $token = getenv('GITHUB_TOKEN');
        $header = self::headerFor($url, is_string($token) ? $token : null);

        if ($header === null) {
            return ['', static function (): void {}];
        }

        $file = (string) tempnam(sys_get_temp_dir(), 'gh-auth-');
        chmod($file, 0o600);
        file_put_contents($file, $header . "\n");

        return [' -H @' . escapeshellarg($file), static function () use ($file): void {
            @unlink($file);
        }];
    }
}
