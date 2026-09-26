<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/** Source-code escape syntaxes for Mode::Escaped. */
enum EscapeFormat: string
{
    /** PHP, Ruby, ES2015 code points: \u{1F600} */
    case Php = 'php';

    /** JavaScript / JSON UTF-16 surrogate pairs: \uD83D\uDE00 (backslash-u, four hex digits, twice) */
    case JavaScript = 'javascript';

    /** Python, C: \U0001F600 */
    case Python = 'python';

    /** CSS content: \1F600 followed by a space */
    case Css = 'css';

    public function encode(int $codepoint): string
    {
        return match ($this) {
            self::Php        => sprintf('\u{%X}', $codepoint),
            self::Python     => $codepoint > 0xFFFF ? sprintf('\U%08X', $codepoint) : sprintf('\u%04X', $codepoint),
            self::Css        => sprintf('\%X ', $codepoint),
            self::JavaScript => $codepoint > 0xFFFF
                ? sprintf('\u%04X\u%04X', 0xD800 + (($codepoint - 0x10000) >> 10), 0xDC00 + (($codepoint - 0x10000) & 0x3FF))
                : sprintf('\u%04X', $codepoint),
        };
    }
}
