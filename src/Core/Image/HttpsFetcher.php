<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Image;

/**
 * The downloader behind ImageStore::install(). Narrow on purpose: https only, to a fixed list of hosts,
 * no redirects, certificate verification on, a per-file size cap and timeouts, a few retries for transient
 * failures. What it returns is still untrusted — ImageStore checks every body against its pinned hash.
 *
 * Uses curl_multi when ext-curl is loaded (parallel within a batch), otherwise PHP's https stream wrapper.
 */
final readonly class HttpsFetcher
{
    /**
     * @param list<string> $hosts
     * @param positive-int $maxBytes
     * @param positive-int $timeout
     * @param int<0, 10> $retries
     */
    public function __construct(
        private array $hosts = ['cdn.jsdelivr.net'],
        private int $maxBytes = 8_388_608,
        private int $timeout = 30,
        private int $retries = 3,
    ) {}

    /**
     * @param list<string> $urls
     *
     * @return array<string, ?string> url => body, or null when it could not be fetched
     */
    public function __invoke(array $urls): array
    {
        $results = array_fill_keys($urls, null);
        $pending = array_values(array_filter($urls, $this->allowed(...)));

        for ($attempt = 0; $attempt <= $this->retries && $pending !== []; $attempt++) {
            if ($attempt > 0) {
                usleep(250_000 * 2 ** $attempt);
            }

            $fetched = extension_loaded('curl') ? $this->curl($pending) : $this->streams($pending);
            $pending = [];

            foreach ($fetched as $url => $body) {
                $body === null ? $pending[] = $url : $results[$url] = $body;
            }
        }

        return $results;
    }

    private function allowed(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['port'])
            && in_array(strtolower($parts['host'] ?? ''), $this->hosts, true);
    }

    /**
     * @param list<string> $urls
     *
     * @return array<string, ?string>
     */
    private function curl(array $urls): array
    {
        $multi = curl_multi_init();
        $handles = [];

        foreach ($urls as $url) {
            $handle = curl_init($url);
            $max = $this->maxBytes;
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS_STR  => 'https',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_USERAGENT      => 'laranail-emojis-image-installer',
                CURLOPT_NOPROGRESS     => false,
                // Abort a body that grows past the cap instead of buffering it.
                CURLOPT_XFERINFOFUNCTION => static fn (mixed $h, int $total, int $received): int => $total > $max || $received > $max ? 1 : 0,
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$url] = $handle;
        }

        do {
            $status = curl_multi_exec($multi, $running);

            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $out = [];

        foreach ($handles as $url => $handle) {
            $body = curl_multi_getcontent($handle);
            $ok = curl_errno($handle) === 0 && curl_getinfo($handle, CURLINFO_RESPONSE_CODE) === 200 && is_string($body);
            $out[$url] = $ok ? $body : null;
            curl_multi_remove_handle($multi, $handle);
        }

        curl_multi_close($multi);

        return $out;
    }

    /**
     * @param list<string> $urls
     *
     * @return array<string, ?string>
     */
    private function streams(array $urls): array
    {
        $out = [];
        $context = stream_context_create([
            'http' => ['timeout' => $this->timeout, 'follow_location' => 0, 'ignore_errors' => false, 'user_agent' => 'laranail-emojis-image-installer'],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        foreach ($urls as $url) {
            $body = @file_get_contents($url, false, $context, 0, $this->maxBytes + 1);
            $out[$url] = is_string($body) && strlen($body) <= $this->maxBytes ? $body : null;
        }

        return $out;
    }
}
