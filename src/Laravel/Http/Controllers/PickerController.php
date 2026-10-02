<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Emojis\Core\Picker\PayloadBuilder;
use Simtabi\Laranail\Emojis\Laravel\Http\Requests\LocaleRequest;

/** GET /picker: everything an emoji picker draws for one locale, policy applied. See PayloadBuilder. */
final readonly class PickerController
{
    public function __construct(private PayloadBuilder $builder) {}

    public function __invoke(LocaleRequest $request): JsonResponse
    {
        $payload = $this->builder->build($request->locale());

        return new JsonResponse(['data' => $payload, 'meta' => ['count' => $payload->count()]]);
    }
}
