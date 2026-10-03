<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Doctor;

use Throwable;
use Illuminate\Contracts\Config\Repository;
use Simtabi\Laranail\Emojis\Laravel\View\PickerConfig;
use Simtabi\Laranail\Emojis\Laravel\View\PickerPayloads;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * The emoji picker's delivery: that the payload builds, how large it is, and whether the configuration can
 * deliver it. Warns, rather than fails, on a payload embedded into every page that is large enough to matter,
 * on `delivery: api` without the API, which falls back to embedding, and on an image set with no coverage data.
 */
final readonly class PickerCheck implements DoctorCheck
{
    /** Above this, an embedded payload is worth moving to the API, which the browser caches. */
    public const int INLINE_BUDGET = 256_000;

    /** package-tools instantiates checks with `new`, so the services are resolved when the check runs. */
    public function __construct(private ?PickerPayloads $payloads = null, private ?PickerConfig $config = null, private ?Repository $settings = null) {}

    public function name(): string
    {
        return 'laranail/emojis picker';
    }

    public function description(): string
    {
        return 'The emoji picker payload builds, and its size and delivery suit the configuration.';
    }

    public function run(): DoctorResult
    {
        try {
            $payloads = $this->payloads ?? app(PickerPayloads::class);
            $config = $this->config ?? app(PickerConfig::class);
            $settings = $this->settings ?? app(Repository::class);
            $payload = $payloads->payload();
            $bytes = strlen(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            return DoctorResult::fail('The picker payload could not be built: ' . $e->getMessage());
        }

        $api = filter_var($settings->get('laranail.emojis.api.enabled'), FILTER_VALIDATE_BOOLEAN);
        $size = sprintf('%d KB', (int) round($bytes / 1024));

        if ($config->delivery === 'api' && ! $api) {
            return DoctorResult::warn("picker.delivery is api but the API is off, so the payload ({$size}) is embedded instead. Set LARANAIL_EMOJIS_API=true, or delivery to inline.", ['bytes' => $bytes]);
        }

        // The dataset has no coverage for JoyPixels, so the payload cannot say which images it lacks: the picker
        // requests some that do not exist, and shows the glyph when they fail.
        if ($payload->images !== null && ($payload->images['set'] ?? null) === 'joypixels') {
            return DoctorResult::warn('The picker falls back to JoyPixels, which has no coverage data: some image requests will 404 before the picker shows the glyph instead. Prefer twemoji, noto or openmoji for picker.image_set.', ['bytes' => $bytes]);
        }

        if ($bytes > self::INLINE_BUDGET && ($config->delivery === 'inline' || ! $api)) {
            return DoctorResult::warn("The picker payload ({$size}) is embedded in every page with a picker. Turn on the API (LARANAIL_EMOJIS_API=true) so the browser fetches and caches it once.", ['bytes' => $bytes]);
        }

        return DoctorResult::pass(sprintf('%s, delivered %s', $size, $api && $config->delivery !== 'inline' ? 'from the API' : 'inline'));
    }
}
