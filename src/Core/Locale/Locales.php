<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Locale;

use Closure;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Options;
use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;
use Simtabi\Laranail\Emojis\Core\Contracts\FailureReporter;
use Simtabi\Laranail\Emojis\Core\Exceptions\DatasetException;

/**
 * Localized names and keywords from the CLDR shards.
 *
 * Resolution: exact tag ("pt-BR"), then script ("zh-Hant" for "zh-TW"/"zh-HK"), then language ("pt"),
 * then the fallback locale, then English. An unshipped locale is an expected fallback, reported once as a
 * tolerated anomaly. A shipped shard that fails to load is a degradable failure: English names are a safe,
 * reduced state, so it is reported and recorded (queryable through FailureReporter::degradations()) and
 * the conversion continues.
 *
 * Skin-tone variants are not stored per locale. Their names are composed from the base emoji's name and
 * the tone component names ("main qui fait coucou : peau moyennement claire"), which is how CLDR derives
 * them and keeps each shard to a few hundred kilobytes.
 */
final class Locales
{
    /** @var array<string, true> shards that failed to load, so a broken file is read once, not per call */
    private array $broken = [];

    /** @param (Closure(): ?string)|null $current the host's current locale, read on every call */
    public function __construct(
        private readonly DatasetStore $data,
        private readonly FailureReporter $reporter,
        private readonly Options $options,
        private readonly ?Closure $current = null,
    ) {}

    /** The shipped locale a requested tag resolves to. */
    public function resolve(?string $locale = null): string
    {
        $requested = $locale ?? ($this->current instanceof Closure ? ($this->current)() : null) ?? $this->options->locale;
        $tag = str_replace('_', '-', trim($requested));

        foreach ($this->candidates($tag) as $candidate) {
            if ($this->shard($candidate) !== null) {
                return $candidate;
            }
        }

        $this->reporter->warn('locale-not-shipped', ['locale' => substr($tag, 0, 16), 'fallback' => $this->options->fallbackLocale]);

        return $this->shard($this->options->fallbackLocale) !== null ? $this->options->fallbackLocale : 'en';
    }

    public function name(Emoji $emoji, ?string $locale = null): string
    {
        $locale = $this->resolve($locale);

        if ($locale === 'en') {
            return $emoji->englishName;
        }

        $names = (array) ($this->shard($locale)['names'] ?? []);

        if ($emoji->baseHexcode === null) {
            $name = $names[$emoji->hexcode] ?? null;

            return is_string($name) && $name !== '' ? $name : $emoji->englishName;
        }

        $base = $names[$emoji->baseHexcode] ?? null;
        $tones = [];

        foreach ($emoji->tones as $tone) {
            $toneName = $names[$tone->hexcode()] ?? null;

            if (! is_string($toneName)) {
                return $emoji->englishName;
            }

            $tones[] = $toneName;
        }

        return is_string($base) ? $base . ': ' . implode(', ', array_values(array_unique($tones))) : $emoji->englishName;
    }

    /** @return list<string> */
    public function keywords(Emoji $emoji, ?string $locale = null): array
    {
        $locale = $this->resolve($locale);
        $keywords = (array) ($this->shard($locale)['keywords'] ?? []);
        $words = $keywords[$emoji->hexcode] ?? $keywords[$emoji->baseHexcode ?? ''] ?? null;

        if (! is_string($words) && $locale !== 'en') {
            return $this->keywords($emoji, 'en');
        }

        // Stored joined with " | " (see .dev/tools/build-dataset.php) to keep the shards small for opcache and lint.
        return is_string($words) && $words !== '' ? explode(' | ', $words) : [];
    }

    /** @return list<string> */
    public function available(): array
    {
        return $this->data->availableLocales();
    }

    /** @return list<string> */
    private function candidates(string $tag): array
    {
        $parts = explode('-', $tag);
        $language = strtolower($parts[0]);
        $candidates = [$tag];

        if ($language === 'zh') {
            $region = strtoupper($parts[1] ?? '');
            $candidates[] = in_array($region, ['TW', 'HK', 'MO', 'HANT'], true) ? 'zh-Hant' : 'zh';
        }

        $candidates[] = $language;

        return array_values(array_unique($candidates));
    }

    /** @return array<string, mixed>|null */
    private function shard(string $locale): ?array
    {
        if (isset($this->broken[$locale])) {
            return null;
        }

        try {
            return $this->data->locale($locale);
        } catch (DatasetException $e) {
            $this->broken[$locale] = true;
            $this->reporter->degraded('locale-shard', $e, ['locale' => $locale, 'fallback' => 'en']);

            return null;
        }
    }
}
