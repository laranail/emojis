<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Doctor;

use Throwable;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * Loads the dataset the way a conversion would and reports anything recorded as degraded (a broken locale
 * shard). A green doctor means the catalogue is complete, the configured image set resolves, and nothing
 * has fallen back silently.
 */
final readonly class DatasetCheck implements DoctorCheck
{
    /** package-tools instantiates checks with `new`, so the service is resolved when the check runs. */
    public function __construct(private ?Emojis $emojis = null) {}

    public function name(): string
    {
        return 'laranail/emojis dataset';
    }

    public function description(): string
    {
        return 'The emoji catalogue loads, scans and renders, the configured image set resolves, and no locale shard has degraded.';
    }

    public function run(): DoctorResult
    {
        try {
            $emojis = $this->emojis ?? app(Emojis::class);
            $count = $emojis->all()->count();
            $roundTrip = $emojis->text('👋🏽 :rocket:')->toAscii();
            $degraded = $emojis->reporter()->degradations();
        } catch (Throwable $e) {
            return DoctorResult::fail('The emoji dataset could not be loaded: ' . $e->getMessage());
        }

        try {
            $emojis->images()->get($emojis->options()->imageSet);
        } catch (Throwable $e) {
            return DoctorResult::fail('The configured image set does not resolve: ' . $e->getMessage(), ['set' => $emojis->options()->imageSet]);
        }

        if ($roundTrip !== ':wave_tone3: :rocket:') {
            return DoctorResult::fail('A known conversion produced unexpected output.', ['got' => $roundTrip]);
        }

        if ($degraded !== []) {
            return DoctorResult::warn('Running degraded: ' . implode(', ', array_keys($degraded)), $degraded);
        }

        return DoctorResult::pass(sprintf('%d emoji, %s', $count, $emojis->datasetVersion()));
    }
}
