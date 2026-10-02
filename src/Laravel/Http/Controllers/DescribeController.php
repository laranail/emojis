<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Laravel\Http\Requests\LocaleRequest;

/** GET /: what this API serves — the dataset, the counts, the locales and groups — and where. */
final readonly class DescribeController
{
    public function __construct(private Emojis $emojis) {}

    public function __invoke(LocaleRequest $request): JsonResponse
    {
        $policy = $this->emojis->options()->policy;
        $emoji = $this->emojis->all()->filter($policy->permits(...));
        $byGroup = array_count_values(array_map(static fn (Emoji $e): string => $e->group->value, $emoji->all()));
        $groups = [];

        foreach (Group::cases() as $group) {
            if (isset($byGroup[$group->value])) {
                $groups[] = ['slug' => $group->value, 'label' => $group->label(), 'count' => $byGroup[$group->value]];
            }
        }

        $link = static fn (string $name): string => route('laranail.emojis.api.' . $name);

        return new JsonResponse(['data' => [
            'dataset' => $this->emojis->datasetVersion(),
            'locale'  => $this->emojis->locales()->resolve($request->locale()),
            'locales' => $this->emojis->availableLocales(),
            'counts'  => [
                'emojis'    => $emoji->count(),
                'symbols'   => $this->emojis->symbols()->count(),
                'kaomoji'   => count($this->emojis->kaomoji()),
                'emoticons' => count($this->emojis->emoticons(risky: true)),
                'tags'      => count($this->emojis->tags()->all()),
            ],
            'groups' => $groups,
            'links'  => [
                'emojis'    => $link('emojis.index'),
                'picker'    => $link('picker'),
                'symbols'   => $link('symbols.index'),
                'kaomoji'   => $link('kaomoji'),
                'emoticons' => $link('emoticons'),
                'tags'      => $link('tags'),
            ],
        ]]);
    }
}
