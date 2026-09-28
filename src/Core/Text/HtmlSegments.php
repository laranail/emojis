<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Text;

/**
 * Splits HTML into markup and text runs without parsing it into a DOM, so the output keeps every byte of
 * markup that was not converted — no reordered attributes, no added <html>/<body>, no normalised entities
 * outside the text runs that actually changed.
 *
 * Text inside <code>, <pre>, <kbd>, <samp>, <script>, <style>, <textarea> and <template> is never converted,
 * and neither are attribute values, comments or CDATA: an emoticon in a code sample is code.
 *
 * Tags are read the way a browser tokenizes them, as far as that matters here: a quoted attribute value may
 * hold ">", a "/" before ">" does not make a non-void element self-closing (so <code/> opens a code
 * element), and the contents of the raw-text elements <script>, <style> and <textarea> are not markup at
 * all — everything up to the matching closing tag is one opaque segment. A "<" that does not start a tag is
 * text.
 *
 * One forward pass: every position is read a bounded number of times, whatever the input.
 */
final class HtmlSegments
{
    /** Elements whose text is left alone. */
    private const array RAW = ['code', 'pre', 'kbd', 'samp', 'script', 'style', 'textarea', 'template'];

    /** Elements whose contents are not parsed as markup: skipped whole, to their closing tag. */
    private const array RAW_TEXT = ['script', 'style', 'textarea'];

    /**
     * A start tag, an end tag, or a <!…>/<?…> declaration. Quoted values are consumed whole so a ">" inside
     * them does not end the tag; an unbalanced quote is consumed as an ordinary character. Possessive, so a
     * failed match never backtracks.
     */
    private const string TAG = '/\G<(?:(\/?)([a-zA-Z][a-zA-Z0-9:-]*)|[!?\/])(?>"[^"]*"|\'[^\']*\'|[^\'">]+|["\'])*+>/';

    /**
     * @return list<array{0: string, 1: bool}> [segment, convertible]
     */
    public static function split(string $html): array
    {
        $segments = [];
        $raw = [];
        $length = strlen($html);
        $lastGt = strrpos($html, '>');
        $text = 0; // start of the pending text run
        $position = 0;

        while (($lt = strpos($html, '<', $position)) !== false) {
            $end = self::markupEnd($html, $lt, $length, $lastGt, $name, $closing);

            if ($end === null) {
                $position = $lt + 1;

                continue;
            }

            if ($lt > $text) {
                $segments[] = [substr($html, $text, $lt - $text), $raw === []];
            }

            if ($name !== null && in_array($name, self::RAW, true)) {
                if ($closing) {
                    $index = array_search($name, $raw, true);

                    if ($index !== false) {
                        array_splice($raw, $index, 1);
                    }
                } elseif (in_array($name, self::RAW_TEXT, true)) {
                    $end = self::rawTextEnd($html, $name, $end, $length);
                } else {
                    $raw[] = $name;
                }
            }

            $segments[] = [substr($html, $lt, $end - $lt), false];
            $text = $position = $end;
        }

        if ($text < $length) {
            $segments[] = [substr($html, $text), $raw === []];
        }

        return $segments;
    }

    /**
     * Where the markup starting at $lt ends, or null when the "<" there is text.
     *
     * @param-out string|null $name the lower-cased element name of a start or end tag
     * @param-out bool $closing whether it is an end tag
     */
    private static function markupEnd(string $html, int $lt, int $length, int|false $lastGt, ?string &$name, ?bool &$closing): ?int
    {
        $name = null;
        $closing = false;

        // An unterminated comment or CDATA section runs to the end of the document, as it does in a browser.
        if (substr_compare($html, '<!--', $lt, 4) === 0) {
            $close = strpos($html, '-->', $lt + 4);

            return $close === false ? $length : $close + 3;
        }

        if (substr_compare($html, '<![CDATA[', $lt, 9) === 0) {
            $close = strpos($html, ']]>', $lt + 9);

            return $close === false ? $length : $close + 3;
        }

        // Without a ">" further on, no tag can close: this "<", and every later one, is text.
        if ($lastGt === false || $lastGt < $lt || preg_match(self::TAG, $html, $m, 0, $lt) !== 1) {
            return null;
        }

        if (($m[2] ?? '') !== '') {
            $name = strtolower($m[2]);
            $closing = $m[1] === '/';
        }

        return $lt + strlen($m[0]);
    }

    /** The end of a raw-text element's closing tag, or of the document when it is never closed. */
    private static function rawTextEnd(string $html, string $name, int $from, int $length): int
    {
        if (preg_match('/<\/' . $name . '(?=[\s\/>])[^>]*+>/i', $html, $m, PREG_OFFSET_CAPTURE, $from) === 1) {
            return $m[0][1] + strlen($m[0][0]);
        }

        return $length;
    }
}
