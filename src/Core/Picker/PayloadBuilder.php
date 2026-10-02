<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Picker;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmoji;

/**
 * Builds the picker payload: every emoji the configured policy permits, grouped in CLDR order, with names
 * and keywords in one locale, then the custom emoji when the policy allows them.
 *
 * The one place that decides what a picker may offer. The HTTP API, the Blade picker's static source and the
 * export command all read it, so they cannot disagree, and none of them can offer an emoji that
 * sanitize() would then strip: `policy.max_version` hides emoji too new for older platforms, which would
 * otherwise draw as empty boxes. Skin-tone variants are not listed; each emoji carries its `skins` map.
 */
final readonly class PayloadBuilder
{
    public function __construct(private Emojis $emojis) {}

    public function build(?string $locale = null): PickerPayload
    {
        $policy = $this->emojis->options()->policy;
        $query = $this->emojis->query();
        $query = $policy->maxVersion instanceof EmojiVersion ? $query->supportedBy($policy->maxVersion) : $query;
        $groups = [];

        foreach ($query->get() as $emoji) {
            if (! $policy->permits($emoji)) {
                continue;
            }

            $groups[$emoji->group->value] ??= ['slug' => $emoji->group->value, 'label' => $emoji->group->label(), 'emoji' => []];
            $groups[$emoji->group->value]['emoji'][] = $this->entry($emoji, $locale);
        }

        $custom = $policy->allowCustom
            ? array_map(static fn (CustomEmoji $c): array => ['name' => $c->name, 'label' => $c->label(), 'image' => $c->image->src, 'fallback' => $c->fallback], $this->emojis->customEmojis())
            : [];

        return new PickerPayload(
            dataset: $this->emojis->datasetVersion(),
            locale: $this->emojis->locales()->resolve($locale),
            groups: array_values(array_filter($groups, static fn (array $g): bool => Group::from($g['slug']) !== Group::Component)),
            custom: $custom,
        );
    }

    /** @return array{emoji: string, hexcode: string, name: string, shortcode: string|null, keywords: list<string>, version: string, skins: array<array-key, string>} */
    private function entry(Emoji $emoji, ?string $locale): array
    {
        return [
            'emoji'     => $emoji->char,
            'hexcode'   => $emoji->hexcode,
            'name'      => $emoji->name($locale),
            'shortcode' => $emoji->shortcode(),
            'keywords'  => $emoji->keywords($locale),
            'version'   => $emoji->version->value,
            'skins'     => array_map(strval(...), $emoji->skins),
        ];
    }
}
