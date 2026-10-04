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
 *
 * The policy is applied to every variant in that map as well as to the base, because a variant can be
 * newer than its base (🤝 is 3.0, its toned forms 14.0) and can be denied or allowed on its own. A variant
 * the policy refuses is dropped from `skins`; a base the policy refuses but some of whose variants it allows
 * is still listed, marked `base: false`, so a picker offers only those variants. `skin_versions` carries the
 * Emoji version of the variants newer than their base, for a client-side version cap: one string when they
 * all share it, otherwise tone key => version.
 */
final readonly class PayloadBuilder
{
    public function __construct(private Emojis $emojis) {}

    /**
     * @param array<string, string> $groupLabels group slug => label in the payload's locale; a group without
     *                                           one keeps its English CLDR name (the Core has no translator)
     */
    public function build(?string $locale = null, array $groupLabels = []): PickerPayload
    {
        $policy = $this->emojis->options()->policy;
        $query = $this->emojis->query();
        $query = $policy->maxVersion instanceof EmojiVersion ? $query->supportedBy($policy->maxVersion) : $query;
        $groups = [];

        foreach ($query->get() as $emoji) {
            $skins = $this->permittedSkins($emoji);
            $base = $policy->permits($emoji);

            if (! $base && $skins === []) {
                continue;
            }

            $groups[$emoji->group->value] ??= ['slug' => $emoji->group->value, 'label' => $groupLabels[$emoji->group->value] ?? $emoji->group->label(), 'emoji' => []];
            $groups[$emoji->group->value]['emoji'][] = $this->entry($emoji, $locale, $skins, $base);
        }

        $custom = $policy->allowCustom
            ? array_map(static fn (CustomEmoji $c): array => ['name' => $c->name, 'label' => $c->label(), 'image' => $c->image->src, 'fallback' => $c->fallback], $this->emojis->customEmojis())
            : [];

        return new PickerPayload(
            dataset: $this->emojis->datasetVersion(),
            locale: $this->emojis->locales()->resolve($locale),
            groups: array_values(array_filter($groups, static fn (array $g): bool => Group::from($g['slug']) !== Group::Component)),
            custom: $custom,
            shortcodeOpen: $this->emojis->options()->shortcodeOpen,
            shortcodeClose: $this->emojis->options()->shortcodeClose,
        );
    }

    /**
     * The variants of an emoji the policy permits, tone key => variant.
     *
     * @return array<array-key, Emoji>
     */
    private function permittedSkins(Emoji $emoji): array
    {
        $policy = $this->emojis->options()->policy;
        $skins = [];

        foreach ($emoji->skins as $key => $hexcode) {
            $variant = $this->emojis->fromHexcode($hexcode);

            if ($variant instanceof Emoji && $policy->permits($variant)) {
                $skins[$key] = $variant;
            }
        }

        return $skins;
    }

    /**
     * @param array<array-key, Emoji> $skins
     *
     * @return array{emoji: string, hexcode: string, name: string, shortcode: string|null, keywords: list<string>, version: string, skins: array<array-key, string>, skin_versions?: string|array<array-key, string>, base?: false}
     */
    private function entry(Emoji $emoji, ?string $locale, array $skins, bool $base): array
    {
        $entry = [
            'emoji'     => $emoji->char,
            'hexcode'   => $emoji->hexcode,
            'name'      => $emoji->name($locale),
            'shortcode' => $emoji->shortcode(),
            'keywords'  => $emoji->keywords($locale),
            'version'   => $emoji->version->value,
            'skins'     => array_map(static fn (Emoji $variant): string => $variant->hexcode, $skins),
        ];

        $newer = array_map(
            static fn (Emoji $variant): string => $variant->version->value,
            array_filter($skins, static fn (Emoji $variant): bool => $variant->version->isNewerThan($emoji->version)),
        );

        if ($newer !== []) {
            // Nearly always every variant shares one version, so a single string keeps the payload small.
            $entry['skin_versions'] = count($newer) === count($skins) && count(array_unique($newer)) === 1 ? reset($newer) : $newer;
        }

        if (! $base) {
            $entry['base'] = false;
        }

        return $entry;
    }
}
