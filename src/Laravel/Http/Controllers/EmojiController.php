<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Simtabi\Laranail\Emojis\Laravel\Http\Requests\LocaleRequest;
use Simtabi\Laranail\Emojis\Laravel\Http\Resources\EmojiResource;
use Simtabi\Laranail\Emojis\Laravel\Http\Requests\EmojiIndexRequest;

/**
 * Read-only emoji lookup over the same Emojis a consumer injects, so nothing is true over HTTP that is not
 * true in PHP. The configured policy applies: an emoji it denies is neither listed nor shown.
 */
final readonly class EmojiController
{
    public function __construct(private Emojis $emojis) {}

    public function index(EmojiIndexRequest $request): AnonymousResourceCollection
    {
        $data = $request->toData();
        $policy = $this->emojis->options()->policy;
        $matches = $data->applyTo($this->emojis->query())->get()->filter($policy->permits(...));

        return EmojiResource::collection($matches->skip($data->offset)->take($data->limit)->all())->additional([
            'meta' => ['total' => $matches->count(), 'limit' => $data->limit, 'offset' => $data->offset],
        ]);
    }

    /**
     * One emoji, by character, hexcode, shortcode or name — anything Emojis::find() takes, percent-encoded.
     * 404 rather than an empty 200, so a typo does not look like an emoji with no fields.
     */
    public function show(LocaleRequest $request, string $key): EmojiResource|JsonResponse
    {
        $emoji = $this->emojis->find($key);

        if (! $emoji instanceof Emoji || ! $this->emojis->options()->policy->permits($emoji)) {
            return new JsonResponse(['message' => 'No emoji matches that key.'], 404);
        }

        return new EmojiResource($emoji);
    }
}
