<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Image;

use DOMNode;
use DOMElement;
use DOMDocument;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidImage;

/**
 * Reduces an SVG to static vector art. It is the second of two defences: the package also only ever emits
 * SVG inside <img src="data:…">, where browsers run no script and load no external resource.
 *
 * The output is rebuilt, not filtered: a new document receives only allow-listed elements in the SVG
 * namespace, allow-listed attributes, and text inside <title>/<desc>. Everything else — <script>,
 * <foreignObject>, <style>, <image>, <a>, animation, event handlers, other namespaces, comments, processing
 * instructions — never reaches the output, so an unknown construct is dropped rather than passed through.
 * The lists were derived from every element and attribute used by the 14,631 SVGs of the four pinned image
 * sets, so real emoji art survives intact.
 *
 * References may only point inside the document: `href`/`xlink:href` must be `#id`, and every `url(…)` in
 * an attribute or style must be `url(#id)`. A DOCTYPE with an internal subset is refused (XXE, entity
 * expansion); a bare external-ID DOCTYPE is dropped, and libxml never loads it (LIBXML_NONET, no DTDLOAD).
 *
 * Trusted mode is for the pinned, hash-verified upstream files only: 170 Illustrator exports there declare
 * simple literal entities for their namespace URIs, which trusted mode expands before parsing.
 */
final class SvgSanitizer
{
    private const string SVG_NS = 'http://www.w3.org/2000/svg';

    private const string XLINK_NS = 'http://www.w3.org/1999/xlink';

    private const array ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc',
        'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon',
        'linearGradient', 'radialGradient', 'stop', 'clipPath', 'mask', 'pattern',
        'filter', 'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feFlood', 'feGaussianBlur',
        'feMerge', 'feMergeNode', 'feMorphology', 'feOffset', 'feFuncA', 'feFuncR', 'feFuncG', 'feFuncB',
    ];

    private const array ATTRIBUTES = [
        'id', 'class', 'style', 'transform', 'viewBox', 'preserveAspectRatio', 'width', 'height', 'x', 'y', 'version',
        'd', 'points', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'fr', 'x1', 'x2', 'y1', 'y2', 'pathLength',
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-opacity', 'stroke-dasharray', 'stroke-dashoffset', 'opacity', 'color',
        'clip-rule', 'clip-path', 'mask', 'filter', 'display', 'visibility', 'overflow', 'isolation',
        'mix-blend-mode', 'paint-order', 'shape-rendering', 'vector-effect', 'enable-background',
        'offset', 'stop-color', 'stop-opacity', 'gradientUnits', 'gradientTransform', 'spreadMethod',
        'clipPathUnits', 'maskUnits', 'maskContentUnits', 'patternUnits', 'patternContentUnits', 'patternTransform',
        'filterUnits', 'primitiveUnits', 'color-interpolation-filters', 'color-interpolation',
        'in', 'in2', 'result', 'mode', 'type', 'values', 'stdDeviation', 'operator', 'k1', 'k2', 'k3', 'k4',
        'dx', 'dy', 'flood-color', 'flood-opacity', 'radius', 'tableValues', 'slope', 'intercept', 'amplitude',
        'exponent', 'edgeMode', 'href',
    ];

    /** Style properties allowed inside a style="" attribute: presentation, nothing that loads or lays out. */
    private const array STYLE_PROPERTIES = [
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-opacity', 'stroke-dasharray', 'stroke-dashoffset', 'opacity', 'color',
        'clip-rule', 'clip-path', 'mask', 'filter', 'display', 'visibility', 'overflow', 'isolation',
        'mix-blend-mode', 'paint-order', 'shape-rendering', 'vector-effect', 'enable-background',
        'stop-color', 'stop-opacity', 'flood-color', 'flood-opacity', 'color-interpolation-filters',
        'color-interpolation',
    ];

    private const int MAX_DEPTH = 64;

    private const int MAX_ENTITIES = 32;

    private int $elements = 0;

    private function __construct(private readonly int $maxElements) {}

    /**
     * @throws InvalidImage when the SVG is malformed, uses a DOCTYPE internal subset, exceeds the element
     *                      limit, or ext-dom is missing
     */
    public static function sanitize(string $svg, int $maxElements = 20_000, bool $trusted = false): string
    {
        if (! class_exists(DOMDocument::class)) {
            throw InvalidImage::svgNeedsDom();
        }

        if (! mb_check_encoding($svg, 'UTF-8') || str_contains($svg, "\0")) {
            throw InvalidImage::unsafeSvg('not UTF-8 text');
        }

        return new self($maxElements)->run(self::withoutDoctype($svg, $trusted));
    }

    /**
     * A DOCTYPE without an internal subset is dropped. One with a subset is refused, except in trusted mode,
     * where literal entities (no markup, no references, no parameter entities) are expanded first.
     */
    private static function withoutDoctype(string $svg, bool $trusted): string
    {
        if (preg_match('/<!DOCTYPE\b[^\[>]*(\[(.*?)\])?\s*>/is', $svg, $match, PREG_OFFSET_CAPTURE) !== 1) {
            if (stripos($svg, '<!ENTITY') !== false || stripos($svg, '<!DOCTYPE') !== false) {
                throw InvalidImage::unsafeSvg('malformed DOCTYPE');
            }

            return $svg;
        }

        $subset = $match[2][0] ?? '';
        $svg = substr_replace($svg, '', $match[0][1], strlen($match[0][0]));

        if (trim($subset) === '') {
            return $svg;
        }

        if (! $trusted) {
            throw InvalidImage::unsafeSvg('DOCTYPE internal subsets (entities) are not allowed');
        }

        preg_match_all('/<!ENTITY\s+([A-Za-z_][\w.-]*)\s+"([^"<&%]*)"\s*>/', $subset, $entities, PREG_SET_ORDER);
        $declared = substr_count($subset, '<!ENTITY');

        if ($declared !== count($entities) || $declared > self::MAX_ENTITIES) {
            throw InvalidImage::unsafeSvg('only literal entities are expanded, even for trusted files');
        }

        $map = [];

        foreach ($entities as $entity) {
            $map['&' . $entity[1] . ';'] = htmlspecialchars($entity[2], ENT_QUOTES | ENT_XML1);
        }

        return strtr($svg, $map);
    }

    /** Keep only allow-listed declarations whose values are safe; drop the rest. */
    private function style(string $style): string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, null);
            $property = strtolower(trim((string) $property));
            $value = trim((string) $value);

            if ($value !== '' && in_array($property, self::STYLE_PROPERTIES, true) && $this->safeValue($value)) {
                $kept[] = $property . ':' . $value;
            }
        }

        return implode(';', $kept);
    }

    /** No escapes, no at-rules, no markup, no scheme, and every url() is a fragment reference. */
    private function safeValue(string $value): bool
    {
        if (preg_match('/[\\\\@<>{}]|expression\s*\(|javascript:|data:|vbscript:/i', $value) === 1) {
            return false;
        }

        $urls = preg_match_all('/url\s*\(/i', $value);
        $fragments = preg_match_all('/url\(\s*([\'"]?)#[A-Za-z_][\w.:-]*\1\s*\)/i', $value);

        return $urls === $fragments;
    }

    private function run(string $svg): string
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $source = new DOMDocument;
            $loaded = $source->loadXML($svg, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }

        $root = $source->documentElement;

        if (! $loaded || ! $root instanceof DOMElement || $root->localName !== 'svg' || ! in_array($root->namespaceURI, [self::SVG_NS, null], true)) {
            throw InvalidImage::unsafeSvg('the root element is not <svg>');
        }

        $out = new DOMDocument('1.0', 'UTF-8');
        $out->appendChild($this->copy($root, $out, 0));

        return (string) $out->saveXML($out->documentElement);
    }

    private function copy(DOMElement $element, DOMDocument $out, int $depth): DOMElement
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidImage::unsafeSvg('nesting deeper than ' . self::MAX_DEPTH);
        }

        if (++$this->elements > $this->maxElements) {
            throw InvalidImage::unsafeSvg('more than ' . $this->maxElements . ' elements');
        }

        $copy = $out->createElementNS(self::SVG_NS, $element->localName ?? throw InvalidImage::unsafeSvg('an element without a name'));

        foreach ($element->attributes ?? [] as $attribute) {
            $this->copyAttribute($copy, $attribute->namespaceURI, $attribute->localName ?? '', $attribute->value);
        }

        $keepsText = in_array($element->localName, ['title', 'desc'], true);

        /** @var DOMNode $child */
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if (in_array($child->namespaceURI, [self::SVG_NS, null], true) && in_array($child->localName, self::ELEMENTS, true)) {
                    $copy->appendChild($this->copy($child, $out, $depth + 1));
                }
            } elseif ($keepsText && $child->nodeType === XML_TEXT_NODE) {
                $copy->appendChild($out->createTextNode($child->textContent ?? ''));
            }
        }

        return $copy;
    }

    private function copyAttribute(DOMElement $copy, ?string $namespace, string $name, string $value): void
    {
        $isHref = $name === 'href' && in_array($namespace, [null, self::XLINK_NS], true);

        if ($namespace !== null && ! $isHref) {
            return;
        }

        if (! in_array($name, self::ATTRIBUTES, true)) {
            return;
        }

        if ($isHref) {
            // Only in-document references; <use href="https://…"> or "data:" would load or embed content.
            if (preg_match('/^#[A-Za-z_][\w.:-]*$/', trim($value)) === 1) {
                $copy->setAttributeNS(self::XLINK_NS, 'xlink:href', trim($value));
            }

            return;
        }

        if ($name === 'style') {
            $value = $this->style($value);

            if ($value === '') {
                return;
            }
        } elseif (! $this->safeValue($value)) {
            return;
        }

        $copy->setAttribute($name, $value);
    }
}
