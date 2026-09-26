<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Render\ImageSets;

use InvalidArgumentException;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Contracts\ImageSet;

/**
 * A self-hosted or third-party set described by a URL template:
 *
 *     new TemplateImageSet('mine', 'https://cdn.example.com/emoji/{hex_lower}.png')
 *
 * Placeholders: {hex} (1F44B-1F3FD), {hex_lower}, {hex_nofe0f} (lowercase, FE0F stripped, dashes — the
 * Twemoji rule), {noto} (emoji_u1f44b_1f3fd), {slug}. Coverage is unknown, so every emoji gets a URL.
 */
final readonly class TemplateImageSet implements ImageSet
{
    public function __construct(
        private string $name,
        private string $template,
        private string $licence = 'unspecified',
    ) {
        if (! str_contains($template, '{')) {
            throw new InvalidArgumentException("Image set template for \"{$name}\" has no placeholder.");
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function url(Emoji $emoji): string
    {
        return strtr($this->template, [
            '{hex}'        => $emoji->hexcode,
            '{hex_lower}'  => strtolower($emoji->hexcode),
            '{hex_nofe0f}' => Filenames::twemoji($emoji),
            '{noto}'       => Filenames::noto($emoji),
            '{slug}'       => $emoji->slug,
        ]);
    }

    public function licence(): string
    {
        return $this->licence;
    }
}
