<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Picker;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Kaomoji;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Symbols\Symbol;
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
     * @param bool $kaomoji add the kaomoji, by group, for a picker's Kaomoji tab (about 2,100)
     * @param bool $symbols add the special characters, by group, for a Symbols tab (about 7,300)
     * @param string|null $imageSet the image set a picker falls back to, or draws everything with; null for none
     * @param list<string> $switchSets more image sets the user may switch to
     * @param bool $englishKeywords add each emoji's English keywords beside a non-English locale's, so a search
     *                              in English finds it too
     */
    public function build(?string $locale = null, array $groupLabels = [], bool $kaomoji = false, bool $symbols = false, ?string $imageSet = null, array $switchSets = [], bool $englishKeywords = false): PickerPayload
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
            $groups[$emoji->group->value]['emoji'][] = $this->entry($emoji, $locale, $skins, $base, $englishKeywords);
        }

        $custom = $policy->allowCustom
            ? array_map(static fn (CustomEmoji $c): array => ['name' => $c->name, 'label' => $c->label(), 'image' => $c->image->src, 'fallback' => $c->fallback], $this->emojis->customEmojis())
            : [];

        $groups = array_values(array_filter($groups, static fn (array $g): bool => Group::from($g['slug']) !== Group::Component));
        $hexcodes = [];

        foreach ($groups as $group) {
            foreach ($group['emoji'] as $entry) {
                $hexcodes[] = $entry['hexcode'];
                array_push($hexcodes, ...array_values($entry['skins']));
            }
        }

        $images = new PickerImages($this->emojis);
        $sets = array_values(array_filter(array_map(
            static fn (string $name): ?array => $images->describe($name, $hexcodes),
            array_values(array_unique(array_filter([$imageSet, ...$switchSets], static fn (?string $n): bool => is_string($n) && $n !== ''))),
        )));

        return new PickerPayload(
            dataset: $this->emojis->datasetVersion(),
            locale: $this->emojis->locales()->resolve($locale),
            groups: $groups,
            custom: $custom,
            shortcodeOpen: $this->emojis->options()->shortcodeOpen,
            shortcodeClose: $this->emojis->options()->shortcodeClose,
            kaomoji: $kaomoji ? $this->kaomoji() : [],
            symbols: $symbols ? $this->symbols() : [],
            images: $imageSet !== null ? ($sets[0] ?? null) : null,
            imageSets: count($sets) > 1 ? $sets : [],
        );
    }

    /**
     * The kaomoji by group, each with what a screen reader announces: its description, else its tags, else
     * the text itself.
     *
     * @return list<array{slug: string, label: string, items: list<array{text: string, name: string}>}>
     */
    private function kaomoji(): array
    {
        $groups = [];

        foreach ($this->emojis->kaomojiGroups() as $slug => $label) {
            $items = array_map(static fn (Kaomoji $k): array => ['text' => $k->value, 'name' => $k->description !== '' ? $k->description : ($k->tags !== [] ? implode(', ', $k->tags) : $k->value)], $this->emojis->kaomoji($slug));

            if ($items !== []) {
                $groups[] = ['slug' => $slug, 'label' => $label, 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * The special characters by group, each with its Unicode name.
     *
     * @return list<array{slug: string, label: string, items: list<array{char: string, name: string}>}>
     */
    private function symbols(): array
    {
        $symbols = $this->emojis->symbols();
        $groups = [];

        foreach ($symbols->groups() as $slug) {
            $items = array_map(static fn (Symbol $s): array => ['char' => $s->char, 'name' => $s->label()], $symbols->group($slug));

            if ($items !== []) {
                $groups[] = ['slug' => $slug, 'label' => ucfirst(str_replace('_', ' ', $slug)), 'items' => $items];
            }
        }

        return $groups;
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
     * @return array{emoji: string, hexcode: string, name: string, shortcode: string|null, keywords: list<string>, version: string, skins: array<array-key, string>, skin_versions?: string|array<array-key, string>, base?: false, emoticons?: list<string>, keywords_en?: list<string>}
     */
    private function entry(Emoji $emoji, ?string $locale, array $skins, bool $base, bool $englishKeywords = false): array
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

        // The emoticons that mean this emoji (":)" for 🙂), for search. Only about a hundred emoji have any.
        $emoticons = $emoji->emoticons();

        if ($emoticons !== []) {
            $entry['emoticons'] = $emoticons;
        }

        if ($englishKeywords && $this->emojis->locales()->resolve($locale) !== 'en') {
            $entry['keywords_en'] = $emoji->keywords('en');
        }

        return $entry;
    }
}
