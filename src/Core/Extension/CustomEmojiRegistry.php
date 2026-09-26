<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Extension;

use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidCustomEmoji;

/**
 * Consumer additions to the catalogue: custom image emoji, extra shortcodes and extra emoticons for
 * existing emoji.
 *
 * Instance-scoped, never static, so two Emojis instances cannot fight over it. Registration happens at
 * boot; freeze() then makes it read-only. In a long-running worker (Octane, RoadRunner) a registration made
 * while handling one request would otherwise leak into every later request on that worker.
 */
final class CustomEmojiRegistry
{
    /** @var array<string, CustomEmoji> code => emoji, aliases included */
    private array $custom = [];

    /** @var array<string, string> code => hexcode */
    private array $shortcodes = [];

    /** @var array<string, string> emoticon => hexcode */
    private array $emoticons = [];

    private bool $frozen = false;

    public function add(CustomEmoji $emoji): self
    {
        $this->guard();

        foreach ([$emoji->name, ...$emoji->aliases] as $code) {
            $this->custom[$code] = $emoji;
        }

        return $this;
    }

    public function shortcode(string $code, string $hexcode): self
    {
        $this->guard();
        $code = strtolower(trim($code, ':'));

        if (preg_match('/^[a-z0-9_+\-]+$/', $code) !== 1) {
            throw InvalidCustomEmoji::badName($code);
        }

        $this->shortcodes[$code] = strtoupper($hexcode);

        return $this;
    }

    public function emoticon(string $emoticon, string $hexcode): self
    {
        $this->guard();

        if (preg_match('/^[\x21-\x7E]{2,}$/', $emoticon) !== 1) {
            throw InvalidCustomEmoji::badName($emoticon);
        }

        $this->emoticons[$emoticon] = strtoupper($hexcode);

        return $this;
    }

    public function freeze(): self
    {
        $this->frozen = true;

        return $this;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    public function find(string $code): ?CustomEmoji
    {
        return $this->custom[strtolower($code)] ?? null;
    }

    /** @return array<string, CustomEmoji> */
    public function all(): array
    {
        return $this->custom;
    }

    /** @return array<string, string> */
    public function shortcodes(): array
    {
        return $this->shortcodes;
    }

    /** @return array<string, string> */
    public function emoticons(): array
    {
        return $this->emoticons;
    }

    private function guard(): void
    {
        if ($this->frozen) {
            throw InvalidCustomEmoji::frozen();
        }
    }
}
