<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Enums;

/**
 * Every form an emoji can be read from or written to.
 *
 * Sources (what a converter can parse): Unicode, Shortcode, Emoticon, HtmlEntity, Escaped, Codepoint,
 * Carrier and Image (only the markup this package emits). Emoji, Text, Ascii, Name, Tag and Auto are targets
 * only: Emoji and Text are reached by parsing Unicode, Ascii, Name and Tag are lossy by design, and Auto is a
 * choice of target.
 */
enum Mode: string
{
    /** Emoji (colour) presentation: the fully-qualified sequence, FE0F where Unicode requires it. */
    case Emoji = 'emoji';

    /** Text (monochrome) presentation via VS15 (FE0E). Unicode defines it for a few hundred characters only. */
    case Text = 'text';

    /** The sequence as found in the input (as-is), or fully-qualified when it came from another form. */
    case Unicode = 'unicode';

    /** Seven-bit ASCII, always: ":shortcode:" from the preset, falling back to the generated slug. */
    case Ascii = 'ascii';

    /** An ASCII emoticon such as ":)" where one exists. */
    case Emoticon = 'emoticon';

    /** A ":shortcode:" from the chosen preset. */
    case Shortcode = 'shortcode';

    /** An <img> from the chosen image set. */
    case Image = 'image';

    /** Numeric character references: "&#x1F600;". */
    case HtmlEntity = 'html_entity';

    /** A Japanese carrier's private-use code point (docomo, au, SoftBank, Google); see Carrier. */
    case Carrier = 'carrier';

    /** A source-code escape; see EscapeFormat. */
    case Escaped = 'escaped';

    /** "U+1F600" notation. */
    case Codepoint = 'codepoint';

    /** The localized CLDR name through a template, "[grinning face]" by default. */
    case Name = 'name';

    /** Emoji where the output can show it (terminal probe), Ascii otherwise. */
    case Auto = 'auto';

    /**
     * A status tag through a template, "[OK]" for ✅ by default; see Emojis::tags(). Only emoji that stand for
     * a status have one, and the rest degrade (to Name by default).
     */
    case Tag = 'tag';

    public function isSource(): bool
    {
        return match ($this) {
            self::Unicode, self::Shortcode, self::Emoticon, self::HtmlEntity, self::Escaped, self::Codepoint, self::Image, self::Carrier => true,
            default                                                                                                                      => false,
        };
    }

    public function producesHtml(): bool
    {
        return $this === self::Image;
    }

    /** True for targets whose output is seven-bit ASCII for every emoji in the dataset. */
    public function isAsciiSafe(): bool
    {
        return match ($this) {
            self::Ascii, self::Shortcode, self::HtmlEntity, self::Escaped, self::Codepoint => true,
            default                                                                        => false,
        };
    }
}
