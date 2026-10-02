<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Security;

use InvalidArgumentException;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Enums\Group;
use Simtabi\Laranail\Emojis\Core\Enums\Subgroup;
use Simtabi\Laranail\Emojis\Core\Support\ConfigInt;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;

/**
 * Which emoji a piece of user content may contain, and how many — the "safety" half of sanitising, as
 * opposed to the security rules the Sanitizer always applies.
 *
 *     EmojiPolicy::permissive()
 *         ->denyGroups(Group::Flags)
 *         ->deny('1F595')                    // hexcodes; skin-tone variants of a denied emoji are denied too
 *         ->supportedUpTo(EmojiVersion::V15_0)
 *         ->maxEmojis(10)
 *         ->replaceWith('*');                // default: remove
 *
 * Immutable. The default allows everything and limits nothing, so a policy is always an explicit choice.
 * Built from config through fromArray(), which takes strings only, so config:cache keeps working.
 */
final readonly class EmojiPolicy
{
    /**
     * @param list<Group> $allowGroups empty = every group
     * @param list<Group> $denyGroups
     * @param list<Subgroup> $denySubgroups
     * @param list<string> $allowOnly hexcodes; empty = no allow-list
     * @param list<string> $deny hexcodes
     */
    public function __construct(
        public array $allowGroups = [],
        public array $denyGroups = [],
        public array $denySubgroups = [],
        public array $allowOnly = [],
        public array $deny = [],
        public ?EmojiVersion $maxVersion = null,
        public bool $allowUnknown = true,
        public bool $allowCustom = true,
        public ?int $maxEmojis = null,
        public ?string $replacement = null,
    ) {
        if ($maxEmojis !== null && $maxEmojis < 0) {
            throw new InvalidArgumentException('maxEmojis must not be negative.');
        }
    }

    public static function permissive(): self
    {
        return new self;
    }

    /** Only these emoji (hexcodes), and nothing unknown or custom — for reaction pickers and the like. */
    public static function only(string ...$hexcodes): self
    {
        return new self(allowOnly: self::normalise($hexcodes), allowUnknown: false, allowCustom: false);
    }

    /**
     * From the `policy` config block: allow_groups, deny_groups, deny_subgroups (enum values), allow_only,
     * deny (hexcodes), max_version, allow_unknown, allow_custom, max_emojis, replacement.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $strings = static function (mixed $value): array {
            $out = [];

            foreach (is_array($value) ? $value : [] as $item) {
                if (is_string($item) && $item !== '') {
                    $out[] = $item;
                }
            }

            return $out;
        };

        return new self(
            allowGroups: array_map(Group::from(...), $strings($config['allow_groups'] ?? [])),
            denyGroups: array_map(Group::from(...), $strings($config['deny_groups'] ?? [])),
            denySubgroups: array_map(Subgroup::from(...), $strings($config['deny_subgroups'] ?? [])),
            allowOnly: self::normalise($strings($config['allow_only'] ?? [])),
            deny: self::normalise($strings($config['deny'] ?? [])),
            maxVersion: is_string($config['max_version'] ?? null) ? EmojiVersion::from($config['max_version']) : null,
            allowUnknown: ($config['allow_unknown'] ?? true) !== false,
            allowCustom: ($config['allow_custom'] ?? true) !== false,
            maxEmojis: ConfigInt::read($config['max_emojis'] ?? null, null),
            replacement: is_string($config['replacement'] ?? null) ? $config['replacement'] : null,
        );
    }

    public function allowGroups(Group ...$groups): self
    {
        return $this->with(['allowGroups' => array_values($groups)]);
    }

    public function denyGroups(Group ...$groups): self
    {
        return $this->with(['denyGroups' => [...$this->denyGroups, ...$groups]]);
    }

    public function denySubgroups(Subgroup ...$subgroups): self
    {
        return $this->with(['denySubgroups' => [...$this->denySubgroups, ...$subgroups]]);
    }

    /** Deny emoji by hexcode. Their skin-tone variants are denied with them. */
    public function deny(string ...$hexcodes): self
    {
        return $this->with(['deny' => [...$this->deny, ...self::normalise($hexcodes)]]);
    }

    public function supportedUpTo(EmojiVersion $version): self
    {
        return $this->with(['maxVersion' => $version]);
    }

    /** Whether pictographs the dataset does not know (newer or vendor emoji) are allowed. */
    public function allowUnknown(bool $allow = true): self
    {
        return $this->with(['allowUnknown' => $allow]);
    }

    public function allowCustom(bool $allow = true): self
    {
        return $this->with(['allowCustom' => $allow]);
    }

    public function maxEmojis(?int $max): self
    {
        return $this->with(['maxEmojis' => $max]);
    }

    /** Replace disallowed emoji with this string instead of removing them. */
    public function replaceWith(?string $replacement): self
    {
        return $this->with(['replacement' => $replacement]);
    }

    public function permits(Emoji $emoji): bool
    {
        $keys = $emoji->baseHexcode === null ? [$emoji->hexcode] : [$emoji->hexcode, $emoji->baseHexcode];

        if (array_intersect($keys, $this->deny) !== []) {
            return false;
        }

        if ($this->allowOnly !== [] && array_intersect($keys, $this->allowOnly) === []) {
            return false;
        }

        if ($this->allowGroups !== [] && ! in_array($emoji->group, $this->allowGroups, true)) {
            return false;
        }

        if (in_array($emoji->group, $this->denyGroups, true) || in_array($emoji->subgroup, $this->denySubgroups, true)) {
            return false;
        }

        return ! $this->maxVersion instanceof EmojiVersion || ! $emoji->version->isNewerThan($this->maxVersion);
    }

    public function isPermissive(): bool
    {
        return $this->allowGroups === [] && $this->denyGroups === [] && $this->denySubgroups === [] && $this->allowOnly === []
            && $this->deny === [] && ! $this->maxVersion instanceof EmojiVersion && $this->allowUnknown && $this->allowCustom && $this->maxEmojis === null;
    }

    /**
     * @param array<array-key, string> $hexcodes
     *
     * @return list<string>
     */
    private static function normalise(array $hexcodes): array
    {
        return array_values(array_map(static fn (string $hex): string => strtoupper(str_replace([' ', '_', 'U+', 'u+'], ['-', '-', '', ''], trim($hex))), $hexcodes));
    }

    /** @param array<string, mixed> $changes */
    private function with(array $changes): self
    {
        /** @phpstan-ignore argument.type (named-argument spread of this class's own state) */
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
