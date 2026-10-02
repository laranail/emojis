<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Laravel\Http\Requests\KaomojiRequest;

/**
 * The catalogues beside the emoji: symbols, kaomoji, emoticons and status tags. Each value object
 * serialises itself (JsonSerializable), so these endpoints and the export command write the same fields.
 */
final readonly class CatalogueController
{
    public function __construct(private Emojis $emojis) {}

    public function symbols(): JsonResponse
    {
        $symbols = $this->emojis->symbols();

        return new JsonResponse(['data' => array_map(
            static fn (string $group): array => ['group' => $group, 'count' => count($symbols->characters($group)), 'characters' => $symbols->characters($group)],
            $symbols->groups(),
        )]);
    }

    /** 404 for an unknown group, rather than an empty list that reads as a group with no members. */
    public function symbolGroup(string $group): JsonResponse
    {
        if (! in_array($group, $this->emojis->symbols()->groups(), true)) {
            return new JsonResponse(['message' => 'No symbol group has that name.'], 404);
        }

        $members = $this->emojis->symbols()->group($group);

        return new JsonResponse(['data' => $members, 'meta' => ['group' => $group, 'count' => count($members)]]);
    }

    public function kaomoji(KaomojiRequest $request): JsonResponse
    {
        $group = $request->group();

        if ($group !== null && ! array_key_exists($group, $this->emojis->kaomojiGroups())) {
            return new JsonResponse(['message' => 'No kaomoji group has that name.'], 404);
        }

        $kaomoji = $this->emojis->kaomoji($group, $request->boolean('ascii'));

        return new JsonResponse(['data' => $kaomoji, 'meta' => ['groups' => $this->emojis->kaomojiGroups(), 'count' => count($kaomoji)]]);
    }

    /** Every emoticon text conversion recognises, opt-in ones included, mapped to its emoji's hexcode. */
    public function emoticons(): JsonResponse
    {
        $map = array_map(static fn (Emoji $e): string => $e->hexcode, $this->emojis->emoticons(risky: true));
        $plain = array_keys($this->emojis->emoticons());

        return new JsonResponse(['data' => $map, 'meta' => ['count' => count($map), 'opt_in_only' => array_values(array_diff(array_keys($map), $plain))]]);
    }

    public function tags(): JsonResponse
    {
        $tags = $this->emojis->tags();

        return new JsonResponse(['data' => $tags->all(), 'meta' => ['groups' => $tags->groups(), 'count' => count($tags->all())]]);
    }
}
