<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Catalogue;

use Countable;
use Traversable;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Support\Macroable;

/**
 * An ordered, immutable list of emoji. Every transform returns a new collection.
 *
 * @implements IteratorAggregate<int, Emoji>
 */
final class EmojiCollection implements Countable, IteratorAggregate, JsonSerializable
{
    use Macroable;

    /** @param list<Emoji> $items */
    public function __construct(private readonly array $items = []) {}

    /** @return list<Emoji> */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?Emoji
    {
        return $this->items[0] ?? null;
    }

    public function last(): ?Emoji
    {
        return $this->items === [] ? null : $this->items[count($this->items) - 1];
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @param callable(Emoji): bool $predicate */
    public function filter(callable $predicate): self
    {
        return new self(array_values(array_filter($this->items, $predicate)));
    }

    /**
     * @template T
     *
     * @param callable(Emoji): T $callback
     *
     * @return list<T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function take(int $limit): self
    {
        return new self(array_slice($this->items, 0, max(0, $limit)));
    }

    public function skip(int $offset): self
    {
        return new self(array_slice($this->items, max(0, $offset)));
    }

    /** Distinct by hexcode, keeping first occurrences. */
    public function unique(): self
    {
        $seen = [];

        return $this->filter(static function (Emoji $emoji) use (&$seen): bool {
            if (isset($seen[$emoji->hexcode])) {
                return false;
            }

            return $seen[$emoji->hexcode] = true;
        });
    }

    /** @return array<string, self> grouped by group slug, in first-seen order */
    public function groupByGroup(): array
    {
        $groups = [];

        foreach ($this->items as $emoji) {
            $groups[$emoji->group->value][] = $emoji;
        }

        return array_map(static fn (array $items): self => new self($items), $groups);
    }

    /** Render each emoji and join them. */
    public function render(Mode $mode = Mode::Emoji, string $separator = ''): string
    {
        return implode($separator, array_map(static fn (Emoji $e): string => $e->render($mode), $this->items));
    }

    /** @return list<string> */
    public function hexcodes(): array
    {
        return array_map(static fn (Emoji $e): string => $e->hexcode, $this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /** @return list<Emoji> */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
