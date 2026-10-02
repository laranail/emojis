<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Tags;

use Stringable;
use JsonSerializable;
use Simtabi\Laranail\Emojis\Core\Enums\TagRole;

/**
 * One status tag — `[OK]`, `[WARN]`, `[FAIL]` — as logs, consoles and test runners write them: the label,
 * its group and role, a one-character text symbol that is never an emoji (`✓`, `✗`, `!`), and the emoji
 * it stands for, primary first.
 *
 * Labels are ASCII and English on purpose: a tag is read by grep, log shippers and alert rules, which match
 * `[ERROR]`, not its translation.
 */
final readonly class Tag implements JsonSerializable, Stringable
{
    /**
     * @param list<string> $emoji the emoji characters, primary first
     * @param list<string> $aliases other labels that find this tag (`WARN` for `WARNING`)
     */
    public function __construct(
        public string $label,
        public string $group,
        public TagRole $role,
        public string $symbol,
        public array $emoji,
        public array $aliases = [],
    ) {}

    public function __toString(): string
    {
        return $this->text();
    }

    /** `[OK]`, or the label through another template: `{tag}` is replaced. */
    public function text(string $template = '[{tag}]'): string
    {
        return strtr($template, ['{tag}' => $this->label]);
    }

    /** The emoji the tag stands for: `✅` for OK. */
    public function primaryEmoji(): string
    {
        return $this->emoji[0];
    }

    /** @return array<string, string|list<string>> */
    public function jsonSerialize(): array
    {
        return [
            'label'   => $this->label,
            'text'    => $this->text(),
            'group'   => $this->group,
            'role'    => $this->role->value,
            'symbol'  => $this->symbol,
            'emoji'   => $this->emoji,
            'aliases' => $this->aliases,
        ];
    }
}
