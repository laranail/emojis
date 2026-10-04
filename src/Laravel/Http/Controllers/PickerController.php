<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Emojis\Laravel\View\PickerPayloads;
use Simtabi\Laranail\Emojis\Laravel\Http\Requests\LocaleRequest;

/** GET /picker: everything an emoji picker draws for one locale, policy applied and group names translated. See PayloadBuilder. */
final readonly class PickerController
{
    public function __construct(private PickerPayloads $payloads) {}

    public function __invoke(LocaleRequest $request): JsonResponse
    {
        $payload = $this->payloads->payload($request->locale());

        return new JsonResponse(['data' => $payload, 'meta' => ['count' => $payload->count()]]);
    }
}
