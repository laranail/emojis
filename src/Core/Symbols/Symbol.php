<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Symbols;

use Stringable;
use JsonSerializable;

/**
 * One special character that is not an emoji — an arrow, a currency sign, a maths operator, a letter with a
 * diacritic, a hieroglyph — with the ways to write it: the character, `U+2192`, its HTML entity, and
 * escapes for CSS, JavaScript and PHP.
 *
 * Only visible characters exist here: controls, format characters (bidi overrides, zero-width joiners, tag
 * characters), private-use and unassigned code points, separators and standalone combining marks are left
 * out of the catalogue, so copying a symbol from it can never paste something invisible.
 */
final readonly class Symbol implements JsonSerializable, Stringable
{
    public string $char;

    public string $hex;

    public function __construct(
        public int $codepoint,
        public string $name,
        public string $category,
        public string $block,
        public ?string $namedEntity,
    ) {
        $this->char = mb_chr($codepoint, 'UTF-8');
        $this->hex = sprintf('%04X', $codepoint);
    }

    public function __toString(): string
    {
        return $this->char;
    }

    /** The Unicode name in sentence case: "Rightwards arrow". */
    public function label(): string
    {
        return ucfirst($this->name);
    }

    public function unicode(): string
    {
        return 'U+' . $this->hex;
    }

    /** The named entity when HTML has one (`&rarr;`), otherwise the numeric reference (`&#x2192;`). */
    public function htmlEntity(): string
    {
        return $this->namedEntity ?? '&#x' . $this->hex . ';';
    }

    public function css(): string
    {
        return '\\' . $this->hex;
    }

    /** A JavaScript string escape: `→`, or a surrogate pair above the BMP. */
    public function javascript(): string
    {
        if ($this->codepoint <= 0xFFFF) {
            return sprintf('\u%04X', $this->codepoint);
        }

        $offset = $this->codepoint - 0x10000;

        return sprintf('\u%04X\u%04X', 0xD800 + ($offset >> 10), 0xDC00 + ($offset & 0x3FF));
    }

    public function php(): string
    {
        return '\u{' . $this->hex . '}';
    }

    /** @return array<string, string|int|null> */
    public function jsonSerialize(): array
    {
        return [
            'char'     => $this->char,
            'unicode'  => $this->unicode(),
            'name'     => $this->name,
            'category' => $this->category,
            'block'    => $this->block,
            'html'     => $this->htmlEntity(),
            'css'      => $this->css(),
        ];
    }
}
