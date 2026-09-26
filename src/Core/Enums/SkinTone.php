<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/** The five Fitzpatrick-based skin tone modifiers (U+1F3FB–U+1F3FF). */
enum SkinTone: int
{
    case Light = 1;
    case MediumLight = 2;
    case Medium = 3;
    case MediumDark = 4;
    case Dark = 5;

    /** "medium-dark", "medium_dark", "MediumDark" or "4". */
    public static function fromName(string $name): ?self
    {
        if (ctype_digit($name)) {
            return self::tryFrom((int) $name);
        }

        $key = strtolower((string) preg_replace('/[^A-Za-z]/', '', $name));

        foreach (self::cases() as $case) {
            if (strtolower($case->name) === $key) {
                return $case;
            }
        }

        return null;
    }

    public static function fromModifier(int $codepoint): ?self
    {
        return self::tryFrom($codepoint - 0x1F3FA);
    }

    public function codepoint(): int
    {
        return 0x1F3FA + $this->value;
    }

    public function hexcode(): string
    {
        return sprintf('%X', $this->codepoint());
    }

    /** Slack's two-token form counts from 2: ":wave::skin-tone-3:" is MediumLight. */
    public function slackIndex(): int
    {
        return $this->value + 1;
    }
}
