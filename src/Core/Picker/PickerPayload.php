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
 * @phpstan-type PickerKaomojiGroup array{slug: string, label: string, items: list<array{text: string, name: string}>}
 * @phpstan-type PickerSymbolGroup array{slug: string, label: string, items: list<array{char: string, name: string}>}
 */
final readonly class PickerPayload implements JsonSerializable
{
    /**
     * @param list<PickerGroup> $groups
     * @param list<PickerCustom> $custom
     * @param list<PickerKaomojiGroup> $kaomoji
     * @param list<PickerSymbolGroup> $symbols
     */
    public function __construct(
        public string $dataset,
        public string $locale,
        public array $groups,
        public array $custom,
        public string $shortcodeOpen = ':',
        public string $shortcodeClose = ':',
        public array $kaomoji = [],
        public array $symbols = [],
    ) {}

    public function count(): int
    {
        return array_sum(array_map(static fn (array $group): int => count($group['emoji']), $this->groups)) + count($this->custom);
    }

    /**
     * `delimiters` are the configured shortcode delimiters, so a picker inserts a custom emoji as a code the
     * scanner reads back (":parrot:" by default, "{{parrot}}" when configured so).
     *
     * `kaomoji` and `symbols` appear only when they were built (a picker with those tabs switched on), so the
     * default payload carries neither.
     *
     * @return array{dataset: string, locale: string, groups: list<PickerGroup>, custom: list<PickerCustom>, delimiters: array{0: string, 1: string}, kaomoji?: list<PickerKaomojiGroup>, symbols?: list<PickerSymbolGroup>}
     */
    public function jsonSerialize(): array
    {
        $out = ['dataset' => $this->dataset, 'locale' => $this->locale, 'groups' => $this->groups, 'custom' => $this->custom, 'delimiters' => [$this->shortcodeOpen, $this->shortcodeClose]];

        if ($this->kaomoji !== []) {
            $out['kaomoji'] = $this->kaomoji;
        }

        if ($this->symbols !== []) {
            $out['symbols'] = $this->symbols;
        }

        return $out;
    }
}
