<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Image;

/** The image formats an emoji image may be. Anything else — AVIF, BMP, TIFF, ICO, HTML — is refused. */
enum ImageType: string
{
    case Png = 'png';
    case Gif = 'gif';
    case Jpeg = 'jpeg';
    case Webp = 'webp';
    case Svg = 'svg';

    public static function fromMime(string $mime): ?self
    {
        return match (strtolower(trim($mime))) {
            'image/png'               => self::Png,
            'image/gif'               => self::Gif,
            'image/jpeg', 'image/jpg' => self::Jpeg,
            'image/webp'              => self::Webp,
            'image/svg+xml'           => self::Svg,
            default                   => null,
        };
    }

    public function mime(): string
    {
        return $this === self::Svg ? 'image/svg+xml' : 'image/' . $this->value;
    }
}
