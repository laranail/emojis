<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Catalogue;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\Subgroup;
use Simtabi\Laranail\Emojis\Core\Locale\Locales;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Enums\SequenceType;

/**
 * A fluent, immutable filter over the catalogue. Nothing runs until get(), first() or count().
 *
 *     $emojis->query()->group(Group::AnimalsAndNature)->search('cat', 'fr')->limit(10)->get();
 *
 * By default skin-tone variants and components are excluded — a picker wants one waving hand, not six.
 * withSkinToneVariants() and withComponents() bring them back.
 */
final readonly class Query
{
    /**
     * @param list<Group> $groups
     * @param list<Subgroup> $subgroups
     * @param list<SequenceType> $types
     */
    public function __construct(
        private Catalogue $catalogue,
        private Locales $locales,
        private array $groups = [],
        private array $subgroups = [],
        private array $types = [],
        private ?EmojiVersion $maxVersion = null,
        private ?EmojiVersion $minVersion = null,
        private bool $variants = false,
        private bool $components = false,
        private ?bool $toneable = null,
        private ?bool $textPresentation = null,
        private ?string $term = null,
        private ?string $locale = null,
        private int $limit = 0,
        private int $offset = 0,
    ) {}

    public function group(Group ...$groups): self
    {
        return $this->with(['groups' => $groups]);
    }

    public function subgroup(Subgroup ...$subgroups): self
    {
        return $this->with(['subgroups' => $subgroups]);
    }

    public function type(SequenceType ...$types): self
    {
        return $this->with(['types' => $types]);
    }

    public function flags(): self
    {
        return $this->type(SequenceType::Flag, SequenceType::Tag);
    }

    /** Only emoji a platform supporting this Emoji version can display. */
    public function supportedBy(EmojiVersion $version): self
    {
        return $this->with(['maxVersion' => $version]);
    }

    /** Only emoji introduced in this version or later ("what's new"). */
    public function since(EmojiVersion $version): self
    {
        return $this->with(['minVersion' => $version]);
    }

    public function withSkinToneVariants(bool $include = true): self
    {
        return $this->with(['variants' => $include]);
    }

    public function withComponents(bool $include = true): self
    {
        return $this->with(['components' => $include]);
    }

    public function skinToneable(bool $toneable = true): self
    {
        return $this->with(['toneable' => $toneable]);
    }

    public function withTextPresentation(bool $has = true): self
    {
        return $this->with(['textPresentation' => $has]);
    }

    /**
     * Rank by relevance to a term across names, keywords, shortcodes and slugs in a locale (the configured
     * one by default): exact name, name prefix, exact shortcode, exact keyword, keyword prefix, substring.
     */
    public function search(string $term, ?string $locale = null): self
    {
        $term = mb_strtolower(trim($term), 'UTF-8');

        return $this->with(['term' => $term === '' ? null : $term, 'locale' => $locale]);
    }

    public function limit(int $limit): self
    {
        return $this->with(['limit' => max(0, $limit)]);
    }

    public function offset(int $offset): self
    {
        return $this->with(['offset' => max(0, $offset)]);
    }

    public function get(): EmojiCollection
    {
        $matches = [];

        foreach ($this->catalogue->all() as $order => $emoji) {
            if (! $this->passes($emoji)) {
                continue;
            }

            if ($this->term === null) {
                $matches[] = [0, $order, $emoji];

                continue;
            }

            $rank = $this->rank($emoji);

            if ($rank !== null) {
                $matches[] = [$rank, $order, $emoji];
            }
        }

        usort($matches, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $items = array_map(static fn (array $m): Emoji => $m[2], $matches);
        $items = array_slice($items, $this->offset, $this->limit > 0 ? $this->limit : null);

        return new EmojiCollection($items);
    }

    public function first(): ?Emoji
    {
        return $this->limit(1)->get()->first();
    }

    public function count(): int
    {
        return $this->limit(0)->offset(0)->get()->count();
    }

    private function passes(Emoji $emoji): bool
    {
        return ($this->components || ! $emoji->isComponent)
            && ($this->variants || ! $emoji->isSkinToneVariant())
            && ($this->groups === [] || in_array($emoji->group, $this->groups, true))
            && ($this->subgroups === [] || in_array($emoji->subgroup, $this->subgroups, true))
            && ($this->types === [] || in_array($emoji->type, $this->types, true))
            && (! $this->maxVersion instanceof EmojiVersion || $emoji->version->isAtMost($this->maxVersion))
            && (! $this->minVersion instanceof EmojiVersion || $this->minVersion->isAtMost($emoji->version))
            && ($this->toneable === null || $emoji->base()->supportsSkinTones() === $this->toneable)
            && ($this->textPresentation === null || $emoji->hasTextPresentation === $this->textPresentation);
    }

    private function rank(Emoji $emoji): ?int
    {
        $term = (string) $this->term;
        $name = mb_strtolower($emoji->name($this->locale), 'UTF-8');
        $english = mb_strtolower($emoji->englishName, 'UTF-8');
        $keywords = array_map(static fn (string $k): string => mb_strtolower($k, 'UTF-8'), $this->locales->keywords($emoji, $this->locale));
        $codes = $emoji->allShortcodes();
        $underscored = str_replace(' ', '_', $term);

        return match (true) {
            $name === $term || $english === $term                                                                                                                     => 0,
            str_starts_with($name, $term) || str_starts_with($english, $term)                                                                                         => 1,
            in_array($underscored, $codes, true) || $emoji->slug === $underscored                                                                                     => 2,
            in_array($term, $keywords, true)                                                                                                                          => 3,
            array_filter($keywords, static fn (string $k): bool => str_starts_with($k, $term)) !== []                                                                 => 4,
            str_contains($name, $term) || str_contains($english, $term) || array_filter($codes, static fn (string $c): bool => str_contains($c, $underscored)) !== [] => 5,
            default                                                                                                                                                   => null,
        };
    }

    /** @param array<string, mixed> $changes */
    private function with(array $changes): self
    {
        $state = get_object_vars($this);

        /** @phpstan-ignore argument.type (named-argument spread of this class's own state) */
        return new self(...array_merge($state, $changes));
    }
}
