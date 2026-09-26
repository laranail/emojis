<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Terminal;

use Simtabi\Laranail\Emojis\Core\Contracts\TerminalProbe;

/**
 * Decides whether the current terminal can show emoji, from the environment alone.
 *
 * The rules follow sindresorhus/is-unicode-supported, the de facto reference:
 *
 *  - an explicit override wins: LARANAIL_EMOJIS=1|0 (or the value passed to the constructor);
 *  - TERM=dumb and the Linux kernel console (TERM=linux) cannot;
 *  - on Windows only known-good hosts can: Windows Terminal (WT_SESSION), VS Code, JetBrains terminals,
 *    Cmder/ConEmu, Alacritty, rxvt-unicode, or an xterm-256color TERM — the legacy console host cannot,
 *    even with a UTF-8 code page, because its font has no emoji glyphs;
 *  - elsewhere, a UTF-8 locale (LC_ALL, LC_CTYPE, LANG) or a macOS Terminal/iTerm session can.
 *
 * NO_COLOR is deliberately not consulted: it turns off ANSI colour, not glyphs.
 *
 * Environment is read from the array given (tests) or getenv().
 */
final readonly class EnvTerminalProbe implements TerminalProbe
{
    /** @param array<string, string>|null $env */
    public function __construct(
        private ?array $env = null,
        private ?bool $override = null,
        private ?bool $windows = null,
    ) {}

    /** The explicit answer, when one was given (constructor argument or LARANAIL_EMOJIS), else null. */
    public function forced(): ?bool
    {
        if ($this->override !== null) {
            return $this->override;
        }

        $forced = $this->get('LARANAIL_EMOJIS');

        return $forced === '' ? null : filter_var($forced, FILTER_VALIDATE_BOOLEAN);
    }

    public function supportsEmoji(): bool
    {
        $forced = $this->forced();

        if ($forced !== null) {
            return $forced;
        }

        $term = strtolower($this->get('TERM'));

        if ($term === 'dumb' || $term === 'linux') {
            return false;
        }

        if ($this->isWindows()) {
            return $this->get('WT_SESSION') !== ''
                || $this->get('TERMINUS_SUBLIME') !== ''
                || $this->get('ConEmuTask') === '{cmd::Cmder}'
                || in_array($this->get('TERM_PROGRAM'), ['vscode', 'Terminus-Sublime'], true)
                || in_array($term, ['xterm-256color', 'alacritty', 'rxvt-unicode', 'rxvt-unicode-256color'], true)
                || $this->get('TERMINAL_EMULATOR') === 'JetBrains-JediTerm';
        }

        if (in_array($this->get('TERM_PROGRAM'), ['Apple_Terminal', 'iTerm.app', 'vscode', 'WezTerm', 'ghostty'], true)) {
            return true;
        }

        foreach (['LC_ALL', 'LC_CTYPE', 'LANG'] as $name) {
            $value = $this->get($name);

            if ($value !== '') {
                return stripos($value, 'UTF-8') !== false || stripos($value, 'utf8') !== false;
            }
        }

        return false;
    }

    /** Windows fonts ship no flag glyphs, so flags show as two letters even in Windows Terminal. */
    public function supportsFlags(): bool
    {
        return $this->supportsEmoji() && ! $this->isWindows();
    }

    private function isWindows(): bool
    {
        return $this->windows ?? PHP_OS_FAMILY === 'Windows';
    }

    private function get(string $name): string
    {
        if ($this->env !== null) {
            return $this->env[$name] ?? '';
        }

        $value = getenv($name);

        return is_string($value) ? $value : '';
    }
}
