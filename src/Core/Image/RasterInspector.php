<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Image;

use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidImage;

/**
 * Identifies an image by its magic bytes and reads its dimensions from the header, without decoding a
 * single pixel — so a 50,000 × 50,000 PNG that compresses to a few kilobytes (a decompression bomb) is
 * refused before anything expands it. No GD or Imagick needed.
 *
 * The declared type (a data URI's MIME, a file extension) is never trusted: the content decides.
 */
final class RasterInspector
{
    /** The type the content really is, or null when it is none of the accepted formats. */
    public static function sniff(string $bytes): ?ImageType
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n")                         => ImageType::Png,
            str_starts_with($bytes, 'GIF87a'), str_starts_with($bytes, 'GIF89a') => ImageType::Gif,
            str_starts_with($bytes, "\xFF\xD8\xFF")                              => ImageType::Jpeg,
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP'   => ImageType::Webp,
            self::looksLikeSvg($bytes)                                           => ImageType::Svg,
            default                                                              => null,
        };
    }

    /**
     * @return array{0: int, 1: int} width, height
     *
     * @throws InvalidImage when the header is truncated or inconsistent
     */
    public static function dimensions(ImageType $type, string $bytes): array
    {
        $size = match ($type) {
            ImageType::Png  => self::png($bytes),
            ImageType::Gif  => strlen($bytes) >= 10 ? self::le16($bytes, 6, 8) : null,
            ImageType::Jpeg => self::jpeg($bytes),
            ImageType::Webp => self::webp($bytes),
            ImageType::Svg  => throw InvalidImage::malformed('SVG has no raster header'),
        };

        if ($size === null || $size[0] < 1 || $size[1] < 1) {
            throw InvalidImage::malformed($type->value . ' header');
        }

        return $size;
    }

    private static function looksLikeSvg(string $bytes): bool
    {
        $head = substr($bytes, 0, 4096);

        if (str_starts_with($head, "\xEF\xBB\xBF")) {
            $head = substr($head, 3);
        }

        // XML declaration, comments and a DOCTYPE may precede the root; the root itself must be <svg.
        $head = (string) preg_replace('/^\s*(?:<\?[^>]*\?>\s*)*(?:<!--.*?-->\s*|<!DOCTYPE[^>\[]*(?:\[[^\]]*\])?\s*>\s*)*/s', '', $head);

        return preg_match('/^<svg[\s>\/]/', $head) === 1;
    }

    /** @return array{0: int, 1: int}|null */
    private static function png(string $b): ?array
    {
        // Signature (8), IHDR length (4), "IHDR" (4), width (4), height (4).
        if (strlen($b) < 24 || substr($b, 12, 4) !== 'IHDR') {
            return null;
        }

        return [self::be32($b, 16), self::be32($b, 20)];
    }

    /** @return array{0: int, 1: int}|null */
    private static function jpeg(string $b): ?array
    {
        $length = strlen($b);
        $i = 2;

        while ($i + 9 < $length) {
            if ($b[$i] !== "\xFF") {
                return null;
            }

            $marker = ord($b[$i + 1]);

            // Padding bytes before a marker, and markers without a length.
            if ($marker === 0xFF) {
                $i++;

                continue;
            }

            if ($marker === 0xD8 || ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                $i += 2;

                continue;
            }

            // Start-of-frame markers carry the size; C4, C8 and CC are not frames.
            if ($marker >= 0xC0 && $marker <= 0xCF && ! in_array($marker, [0xC4, 0xC8, 0xCC], true)) {
                return [self::be16($b, $i + 7), self::be16($b, $i + 5)];
            }

            $segment = self::be16($b, $i + 2);

            if ($segment < 2) {
                return null;
            }

            $i += 2 + $segment;
        }

        return null;
    }

    /** @return array{0: int, 1: int}|null */
    private static function webp(string $b): ?array
    {
        if (strlen($b) < 30) {
            return null;
        }

        return match (substr($b, 12, 4)) {
            // Lossy: 14-bit width/height after the frame tag and start code.
            'VP8 ' => substr($b, 23, 3) === "\x9D\x01\x2A" ? [self::le16($b, 26, 28)[0] & 0x3FFF, self::le16($b, 26, 28)[1] & 0x3FFF] : null,
            // Lossless: 14-bit width-1 and height-1 packed after the 0x2F signature.
            'VP8L' => $b[20] === "\x2F" ? self::vp8l($b) : null,
            // Extended: 24-bit canvas width-1 and height-1.
            'VP8X'  => [self::le24($b, 24) + 1, self::le24($b, 27) + 1],
            default => null,
        };
    }

    /** @return array{0: int, 1: int} */
    private static function vp8l(string $b): array
    {
        $bits = ord($b[21]) | (ord($b[22]) << 8) | (ord($b[23]) << 16) | (ord($b[24]) << 24);

        return [($bits & 0x3FFF) + 1, (($bits >> 14) & 0x3FFF) + 1];
    }

    private static function be32(string $b, int $at): int
    {
        return (ord($b[$at]) << 24) | (ord($b[$at + 1]) << 16) | (ord($b[$at + 2]) << 8) | ord($b[$at + 3]);
    }

    private static function be16(string $b, int $at): int
    {
        return (ord($b[$at]) << 8) | ord($b[$at + 1]);
    }

    private static function le24(string $b, int $at): int
    {
        return ord($b[$at]) | (ord($b[$at + 1]) << 8) | (ord($b[$at + 2]) << 16);
    }

    /** @return array{0: int, 1: int} two little-endian 16-bit values at $a and $b */
    private static function le16(string $s, int $a, int $b): array
    {
        return [ord($s[$a]) | (ord($s[$a + 1]) << 8), ord($s[$b]) | (ord($s[$b + 1]) << 8)];
    }
}
