<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Tags;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiId;
use Simtabi\Laranail\Emojis\Core\Enums\TagRole;
use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;
use Simtabi\Laranail\Emojis\Core\Catalogue\Catalogue;

/**
 * The status tags — `[OK]`, `[WARN]`, `[FAIL]`, `[SKIPPED]` — in groups: outcome, severity, task state, test
 * result, change and other. Curated in database/sources/curated/tags.json from what consoles, test runners
 * and log levels already print (Symfony's `[OK]`/`[ERROR]`, Laravel's `DONE`/`FAIL`, Pest's `PASS`, the
 * PSR-3 levels), each with the emoji it stands for. Mode::Tag writes them in place of emoji.
 *
 * Labels and aliases are unique across every group, and each emoji is written as at most one tag.
 */
final class Tags
{
    /** Positions in a tag record, in the order of the shard's `fields` (pinned by TagsTest). */
    private const int GROUP = 0;

    private const int ROLE = 1;

    private const int SYMBOL = 2;

    private const int EMOJI = 3;

    private const int ALIASES = 4;

    /** @var array<string, Tag> */
    private array $built = [];

    /** @var array<string, string>|null alias => label */
    private ?array $aliases = null;

    public function __construct(
        private readonly DatasetStore $data,
        private readonly Catalogue $catalogue,
    ) {}

    /** @return array<string, string> group slug => label, in display order */
    public function groups(): array
    {
        return $this->data->tags()['groups'];
    }

    /** @return list<Tag> every tag, in display order */
    public function all(): array
    {
        return array_map($this->build(...), array_keys($this->data->tags()['tags']));
    }

    /** @return list<Tag> the group's tags, in display order; [] for an unknown group */
    public function group(string $slug): array
    {
        return array_values(array_filter($this->all(), static fn (Tag $tag): bool => $tag->group === $slug));
    }

    /** By label or alias, in any case and with or without brackets: `OK`, `warn`, `[FAIL]`. */
    public function get(string $label): ?Tag
    {
        $key = strtoupper(trim(trim($label), '[]'));
        $this->aliases ??= $this->buildAliases();
        $resolved = isset($this->data->tags()['tags'][$key]) ? $key : ($this->aliases[$key] ?? null);

        return $resolved === null ? null : $this->build($resolved);
    }

    /**
     * The tag an emoji is written as — `✅` → OK — or null when it has none. A skin-toned emoji falls back
     * to its base (`👍🏽` → YES). Takes anything Emojis::find() does.
     */
    public function for(Emoji|EmojiId|string $emoji): ?Tag
    {
        $emoji = $emoji instanceof Emoji ? $emoji : $this->catalogue->find($emoji);

        if (! $emoji instanceof Emoji) {
            return null;
        }

        $map = $this->data->tags()['map'];
        $label = $map[$emoji->char] ?? ($emoji->baseHexcode === null ? null : $map[$emoji->base()->char] ?? null);

        return $label === null ? null : $this->build($label);
    }

    /**
     * Tags whose label or an alias contains the term, label prefix matches first. A limit of 0 means no limit.
     *
     * @return list<Tag>
     */
    public function search(string $term, int $limit = 0): array
    {
        $term = strtoupper(trim(trim($term), '[]'));

        if ($term === '') {
            return [];
        }

        $prefix = [];
        $contains = [];

        foreach ($this->all() as $tag) {
            $names = [$tag->label, ...$tag->aliases];

            match (true) {
                array_filter($names, static fn (string $n): bool => str_starts_with($n, $term)) !== [] => $prefix[] = $tag,
                array_filter($names, static fn (string $n): bool => str_contains($n, $term)) !== []    => $contains[] = $tag,
                default                                                                                => null,
            };
        }

        $found = [...$prefix, ...$contains];

        return $limit > 0 ? array_slice($found, 0, $limit) : $found;
    }

    private function build(string $label): Tag
    {
        if (isset($this->built[$label])) {
            return $this->built[$label];
        }

        $record = $this->data->tags()['tags'][$label];

        return $this->built[$label] = new Tag(
            label: $label,
            group: $record[self::GROUP],
            role: TagRole::from($record[self::ROLE]),
            symbol: $record[self::SYMBOL],
            emoji: explode(' ', $record[self::EMOJI]),
            aliases: $record[self::ALIASES] === '' ? [] : explode('|', $record[self::ALIASES]),
        );
    }

    /** @return array<string, string> */
    private function buildAliases(): array
    {
        $aliases = [];

        foreach ($this->data->tags()['tags'] as $label => $record) {
            foreach ($record[self::ALIASES] === '' ? [] : explode('|', $record[self::ALIASES]) as $alias) {
                $aliases[$alias] = $label;
            }
        }

        return $aliases;
    }
}
