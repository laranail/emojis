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
 */
final class HtmlSegments
{
    private const array RAW = ['code', 'pre', 'kbd', 'samp', 'script', 'style', 'textarea', 'template'];

    /**
     * @return list<array{0: string, 1: bool}> [segment, convertible]
     */
    public static function split(string $html): array
    {
        $parts = preg_split('/(<!--.*?-->|<!\[CDATA\[.*?\]\]>|<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return [[$html, false]];
        }

        $segments = [];
        $raw = [];

        foreach ($parts as $part) {
            if ($part[0] === '<') {
                if (preg_match('/^<\s*(\/?)\s*([a-zA-Z][a-zA-Z0-9-]*)/', $part, $m) === 1) {
                    $name = strtolower($m[2]);

                    if (in_array($name, self::RAW, true)) {
                        if ($m[1] === '/') {
                            $index = array_search($name, $raw, true);

                            if ($index !== false) {
                                array_splice($raw, $index, 1);
                            }
                        } elseif (! str_ends_with(rtrim($part, '> '), '/')) {
                            $raw[] = $name;
                        }
                    }
                }

                $segments[] = [$part, false];

                continue;
            }

            $segments[] = [$part, $raw === []];
        }

        return $segments;
    }
}
