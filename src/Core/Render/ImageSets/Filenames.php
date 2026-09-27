<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Render\ImageSets;

use Simtabi\Laranail\Emojis\Core\Emoji;

/** The filename rules each publisher uses. Verified against the published file listings the dataset pins. */
final class Filenames
{
    /** Twemoji strips every FE0F unless the sequence contains a ZWJ: 2764.svg, 2764-fe0f-200d-1f525.svg. */
    public static function twemoji(Emoji $emoji): string
    {
        $keep = in_array(0x200D, $emoji->codepoints, true)
            ? $emoji->codepoints
            : array_filter($emoji->codepoints, static fn (int $cp): bool => $cp !== 0xFE0F);

        return implode('-', array_map(static fn (int $cp): string => sprintf('%x', $cp), $keep));
    }

    /** Noto: emoji_u + lowercase, zero-padded to 4, underscore-joined, FE0F always stripped. */
    public static function noto(Emoji $emoji): string
    {
        $parts = [];

        foreach ($emoji->codepoints as $cp) {
            if ($cp !== 0xFE0F) {
                $parts[] = sprintf('%04x', $cp);
            }
        }

        return 'emoji_u' . implode('_', $parts);
    }

    /** OpenMoji: uppercase hexcode as emojibase writes it (FE0F kept only inside sequences). */
    public static function openmoji(Emoji $emoji): string
    {
        return count($emoji->codepoints) === 2 && $emoji->codepoints[1] === 0xFE0F ? sprintf('%04X', $emoji->codepoints[0]) : $emoji->hexcode;
    }
}
