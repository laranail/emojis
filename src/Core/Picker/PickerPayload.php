<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Picker;

use JsonSerializable;

/**
 * Everything an emoji picker draws, for one locale: the emoji by group, then the custom ones. Built by
 * PayloadBuilder, which applies the emoji policy, so a picker never offers what the server would refuse.
 *
 * @phpstan-type PickerEmoji array{emoji: string, hexcode: string, name: string, shortcode: string|null, keywords: list<string>, version: string, skins: array<array-key, string>, skin_versions?: string|array<array-key, string>, base?: false}
 * @phpstan-type PickerGroup array{slug: string, label: string, emoji: list<PickerEmoji>}
 * @phpstan-type PickerCustom array{name: string, label: string, image: string, fallback: string|null}
 */
final readonly class PickerPayload implements JsonSerializable
{
    /**
     * @param list<PickerGroup> $groups
     * @param list<PickerCustom> $custom
     */
    public function __construct(
        public string $dataset,
        public string $locale,
        public array $groups,
        public array $custom,
        public string $shortcodeOpen = ':',
        public string $shortcodeClose = ':',
    ) {}

    public function count(): int
    {
        return array_sum(array_map(static fn (array $group): int => count($group['emoji']), $this->groups)) + count($this->custom);
    }

    /**
     * `delimiters` are the configured shortcode delimiters, so a picker inserts a custom emoji as a code the
     * scanner reads back (":parrot:" by default, "{{parrot}}" when configured so).
     *
     * @return array{dataset: string, locale: string, groups: list<PickerGroup>, custom: list<PickerCustom>, delimiters: array{0: string, 1: string}}
     */
    public function jsonSerialize(): array
    {
        return ['dataset' => $this->dataset, 'locale' => $this->locale, 'groups' => $this->groups, 'custom' => $this->custom, 'delimiters' => [$this->shortcodeOpen, $this->shortcodeClose]];
    }
}
