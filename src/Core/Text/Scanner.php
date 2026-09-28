<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Text;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\Carrier;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;
use Simtabi\Laranail\Emojis\Core\Catalogue\Catalogue;
use Simtabi\Laranail\Emojis\Core\Enums\Qualification;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmoji;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmojiRegistry;

/**
 * Finds emoji in text, in every form a converter can read.
 *
 * The Unicode pass does not use a regex over the emoji set. An alternation of ~5,200 sequences exceeds
 * PCRE's compiled-pattern limit on common builds, and PCRE's Unicode emoji properties are missing from
 * the PCRE versions many PHP builds still link (10.36 has none of them). Instead:
 *
 *   1. a generated character class of every code point that can start a sequence finds candidates;
 *   2. at each candidate, a hash lookup probes each distinct sequence byte length, longest first, so
 *      👨‍👩‍👧‍👦 wins over 👨, and ❤️‍🔥 over ❤️.
 *
 * That is O(candidates × lengths) with isset() as the inner loop, independent of PCRE JIT, and it gives the
 * same answer on every PHP build. Only exact dataset sequences match; a pictograph the dataset does not
 * know is found by pictographs(), used when sanitising.
 *
 * Text-default single characters (©, ®, ™, ☺ without FE0F) are skipped unless asked for: a "© 2026" footer
 * is not an emoji.
 */
final class Scanner
{
    private ?string $emoticonPattern = null;

    private ?bool $emoticonPatternRisky = null;

    public function __construct(
        private readonly DatasetStore $data,
        private readonly Catalogue $catalogue,
        private readonly CustomEmojiRegistry $custom,
    ) {}

    /**
     * @param list<Token> $tokens
     *
     * @return list<Token>
     */
    public static function resolveOverlaps(array $tokens): array
    {
        usort($tokens, static fn (Token $a, Token $b): int => [$a->offset, -$a->length] <=> [$b->offset, -$b->length]);
        $accepted = [];
        $cursor = 0;

        foreach ($tokens as $token) {
            if ($token->offset >= $cursor) {
                $accepted[] = $token;
                $cursor = $token->end();
            }
        }

        return $accepted;
    }

    /** @return list<Token> non-overlapping, in offset order */
    public function scan(string $text, ScanOptions $options): array
    {
        if ($text === '') {
            return [];
        }

        $tokens = [];

        foreach ($options->sources as $source) {
            array_push($tokens, ...match ($source) {
                Mode::Unicode    => $this->unicode($text, $options->textPresentation),
                Mode::Shortcode  => $this->shortcodes($text),
                Mode::Emoticon   => $this->emoticons($text, $options->riskyEmoticons),
                Mode::HtmlEntity => $this->decoded($text, Mode::HtmlEntity, '/&#(?:[xX]([0-9A-Fa-f]{1,6})|([0-9]{1,7}));/', ''),
                Mode::Escaped    => $this->escaped($text),
                Mode::Codepoint  => $this->decoded($text, Mode::Codepoint, '/[Uu]\+([0-9A-Fa-f]{4,6})/', '[ ,]*'),
                Mode::Image      => $this->images($text),
                Mode::Carrier    => $this->carrier($text, $options->carrier),
                default          => [],
            });
        }

        return self::resolveOverlaps($tokens);
    }

    /**
     * Pictographic runs the catalogue does not know — future emoji, vendor sequences such as 🐱‍👤, stray
     * modifiers, variation selectors and tags — excluding anything inside the given known tokens.
     *
     * @param list<Token> $known
     *
     * @return list<Token>
     */
    public function pictographs(string $text, array $known): array
    {
        $pictographic = $this->data->pictographicClass();
        $components = $this->data->componentClass();
        $pattern = '/[' . $pictographic . '](?:[' . $components . ']|\x{200D}[' . $pictographic . '])*|[' . $components . ']+/u';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $found = [];
        $sequences = $this->data->sequences();

        // One pass over both lists. Candidates arrive in offset order and do not overlap, so their ends rise
        // too; every known token starting before the current candidate ends is folded into $reach (the
        // furthest end seen), and the candidate overlaps a known token exactly when $reach passes its start.
        usort($known, static fn (Token $a, Token $b): int => $a->offset <=> $b->offset);
        $next = 0;
        $total = count($known);
        $reach = -1;

        foreach ($matches[0] as [$match, $offset]) {
            $sequence = $sequences[$match] ?? null;

            if ($sequence !== null && $sequence[1] === Qualification::TextDefault->value) {
                continue; // a lone ©, ®, ™ is text
            }

            $candidate = new Token($offset, strlen($match), Mode::Unicode);

            while ($next < $total && $known[$next]->offset < $candidate->end()) {
                $reach = max($reach, $known[$next]->end());
                $next++;
            }

            if ($reach > $candidate->offset) {
                continue;
            }

            $found[] = $candidate;
        }

        return $found;
    }

    /** @return list<Token> */
    private function unicode(string $text, bool $textPresentation): array
    {
        if (preg_match_all('/[' . $this->data->startClass() . ']/u', $text, $candidates, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $sequences = $this->data->sequences();
        $lengths = $this->data->sequenceLengths();
        $size = strlen($text);
        $tokens = [];
        $cursor = 0;

        foreach ($candidates[0] as [, $offset]) {
            if ($offset < $cursor) {
                continue;
            }

            foreach ($lengths as $length) {
                if ($offset + $length > $size) {
                    continue;
                }

                $entry = $sequences[substr($text, $offset, $length)] ?? null;

                if ($entry === null) {
                    continue;
                }

                $quality = Qualification::from($entry[1]);

                if ($quality === Qualification::TextDefault && ! $textPresentation) {
                    continue;
                }

                $tokens[] = new Token($offset, $length, Mode::Unicode, $entry[0], null, $quality);
                $cursor = $offset + $length;

                break;
            }
        }

        return $tokens;
    }

    /**
     * ":code:" in any preset, with Slack's two-token skin tone (":wave::skin-tone-3:").
     *
     * A code must not be glued to a word, a path or a preceding colon, so "12:30:45", "std::vector",
     * "laranail::emojis.search" and "/users/:id:" are left alone; back-to-back codes (":a::b:") still
     * match because a preceding colon is accepted when it closes the previous code.
     *
     * @return list<Token>
     */
    private function shortcodes(string $text): array
    {
        if (preg_match_all('/:([A-Za-z0-9_+\-]+):(?::skin-tone-([2-6]):)?/', $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [];
        }

        $tokens = [];
        $lastEnd = -1;

        foreach ($matches as $match) {
            [$whole, $offset] = $match[0];
            $end = $offset + strlen($whole);
            $before = $offset > 0 ? $text[$offset - 1] : '';
            $after = $text[$end] ?? '';

            if (($before !== '' && preg_match('/[A-Za-z0-9_\/]/', $before) === 1)
                || ($before === ':' && $offset !== $lastEnd)
                || ($after !== '' && preg_match('/[A-Za-z0-9_]/', $after) === 1)) {
                continue;
            }

            $code = strtolower($match[1][0]);
            $plainEnd = $offset + strlen($match[1][0]) + 2;
            $custom = $this->custom->find($code);

            if ($custom instanceof CustomEmoji) {
                $tokens[] = new Token($offset, $plainEnd - $offset, Mode::Shortcode, null, $custom);
                $lastEnd = $plainEnd;

                continue;
            }

            $emoji = $this->catalogue->byShortcode($code);

            if (! $emoji instanceof Emoji) {
                continue;
            }

            $hasTone = isset($match[2]) && $match[2][1] >= 0;

            if ($hasTone && $emoji->base()->supportsSkinTones()) {
                $emoji = $emoji->withSkinTone(SkinTone::from((int) $match[2][0] - 1));
            } else {
                $end = $plainEnd; // a tone suffix on an emoji that takes none stays in the text
            }

            $tokens[] = new Token($offset, $end - $offset, Mode::Shortcode, $emoji->hexcode);
            $lastEnd = $end;
        }

        return $tokens;
    }

    /**
     * ASCII emoticons, only as whole words: preceded by the start or whitespace, followed by the end,
     * whitespace or closing punctuation. So "http://", "foo();)" and "x<3" never match.
     *
     * @return list<Token>
     */
    private function emoticons(string $text, bool $risky): array
    {
        if ($this->emoticonPattern === null || $this->emoticonPatternRisky !== $risky) {
            $excluded = $risky ? [] : array_flip($this->data->riskyEmoticons());
            $all = [];

            foreach (array_keys([...$this->data->emoticonMap(), ...$this->custom->emoticons()]) as $emoticon) {
                if (! isset($excluded[(string) $emoticon])) {
                    $all[] = (string) $emoticon;
                }
            }

            usort($all, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            $this->emoticonPattern = $all === [] ? '' : '~(?<!\S)(?:' . implode('|', array_map(static fn (string $e): string => preg_quote($e, '~'), $all)) . ')(?![^\s.,!?;:)\]"\'])~';
            $this->emoticonPatternRisky = $risky;
        }

        if ($this->emoticonPattern === '' || preg_match_all($this->emoticonPattern, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $tokens = [];

        foreach ($matches[0] as [$match, $offset]) {
            $emoji = $this->catalogue->byEmoticon($match);

            if ($emoji instanceof Emoji) {
                $tokens[] = new Token($offset, strlen($match), Mode::Emoticon, $emoji->hexcode);
            }
        }

        return $tokens;
    }

    /** @return list<Token> PHP \u{…}, JavaScript/JSON surrogate pairs and \uXXXX, Python \UXXXXXXXX */
    private function escaped(string $text): array
    {
        $pattern = '/\\\\u\{([0-9A-Fa-f]{1,6})\}|\\\\U([0-9A-Fa-f]{8})|\\\\u([dD][89abAB][0-9A-Fa-f]{2})\\\\u([dD][c-fC-F][0-9A-Fa-f]{2})|\\\\u([0-9A-Fa-f]{4})/';

        return $this->decodedWith($text, Mode::Escaped, $pattern, '', static function (array $m): int {
            if (($m[1][0] ?? '') !== '') {
                return (int) hexdec($m[1][0]);
            }

            if (($m[2][0] ?? '') !== '') {
                return (int) hexdec($m[2][0]);
            }

            if (($m[3][0] ?? '') !== '') {
                return 0x10000 + (((int) hexdec($m[3][0]) - 0xD800) << 10) + ((int) hexdec($m[4][0]) - 0xDC00);
            }

            return (int) hexdec($m[5][0]);
        });
    }

    /** @return list<Token> */
    private function decoded(string $text, Mode $mode, string $unit, string $separator): array
    {
        return $this->decodedWith($text, $mode, $unit, $separator, static fn (array $m): int => ($m[1][0] ?? '') !== '' ? (int) hexdec($m[1][0]) : (int) ($m[2][0] ?? 0));
    }

    /**
     * Finds runs of escaped code points, decodes each run, runs the Unicode pass over the decoded text and
     * maps every emoji found back to the escapes it came from. So "&#x1F468;&#x200D;&#x1F4BB;" is one 👨‍💻,
     * and "&#x1F600;&#x1F601;" is two emoji, not an unknown sequence.
     *
     * @param callable(array<int|string, array{0: string, 1: int}>): int $decode
     *
     * @return list<Token>
     */
    private function decodedWith(string $text, Mode $mode, string $unit, string $separator, callable $decode): array
    {
        if (preg_match_all($unit, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false || $matches === []) {
            return [];
        }

        $runs = [];
        $run = [];
        $previousEnd = -1;

        foreach ($matches as $match) {
            [$whole, $offset] = $match[0];
            $gap = $previousEnd >= 0 ? substr($text, $previousEnd, $offset - $previousEnd) : null;
            $joined = $gap !== null && ($gap === '' || ($separator !== '' && preg_match('/^' . $separator . '$/', $gap) === 1));

            if (! $joined && $run !== []) {
                $runs[] = $run;
                $run = [];
            }

            $cp = $decode($match);

            if ($cp > 0 && $cp <= 0x10FFFF && ($cp < 0xD800 || $cp > 0xDFFF)) {
                $run[] = [$cp, $offset, $offset + strlen($whole)];
            } elseif ($run !== []) {
                $runs[] = $run;
                $run = [];
            }

            $previousEnd = $offset + strlen($whole);
        }

        if ($run !== []) {
            $runs[] = $run;
        }

        $tokens = [];

        foreach ($runs as $units) {
            $decoded = '';
            $byteToUnit = [];

            foreach ($units as $index => [$cp]) {
                $char = mb_chr($cp, 'UTF-8');
                $byteToUnit[strlen($decoded)] = $index;
                $decoded .= $char;
            }

            $byteToUnit[strlen($decoded)] = count($units);

            foreach ($this->unicode($decoded, true) as $inner) {
                $first = $byteToUnit[$inner->offset] ?? null;
                $last = $byteToUnit[$inner->end()] ?? null;

                if ($first === null || $last === null) {
                    continue;
                }

                $start = $units[$first][1];
                $end = $units[$last - 1][2];
                $tokens[] = new Token($start, $end - $start, $mode, $inner->hexcode, null, $inner->qualification);
            }
        }

        return $tokens;
    }

    /**
     * Private-use code points of one carrier. The carrier must be named because docomo, au and SoftBank
     * overlap; Google's plane-15 range (the default) does not.
     *
     * @return list<Token>
     */
    private function carrier(string $text, Carrier $carrier): array
    {
        if (preg_match_all('/[\\x{E000}-\\x{F8FF}\\x{F0000}-\\x{FFFFD}]/u', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $reads = $this->data->carrierReads($carrier->value);
        $tokens = [];

        foreach ($matches[0] as [$char, $offset]) {
            $hex = $reads[sprintf('%04X', mb_ord($char, 'UTF-8'))] ?? null;

            if ($hex !== null) {
                $tokens[] = new Token($offset, strlen($char), Mode::Carrier, $hex);
            }
        }

        return $tokens;
    }

    /** @return list<Token> only the markup this package emits: <img …> or a fitted <svg …>, keyed by data-laranail-emoji */
    private function images(string $text): array
    {
        if (preg_match_all('/<img\b[^>]*\bdata-laranail-emoji="([0-9A-Fa-f-]+|:[a-z0-9_+\-]+:)"[^>]*>|<svg\b[^>]*\bdata-laranail-emoji="([0-9A-Fa-f-]+)"[^>]*>.*?<\/svg>/is', $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [];
        }

        $tokens = [];

        foreach ($matches as $match) {
            [$whole, $offset] = $match[0];
            $key = ($match[1][0] ?? '') !== '' ? $match[1][0] : ($match[2][0] ?? '');

            if ($key[0] === ':') {
                $custom = $this->custom->find(trim($key, ':'));

                if ($custom instanceof CustomEmoji) {
                    $tokens[] = new Token($offset, strlen($whole), Mode::Image, null, $custom);
                }

                continue;
            }

            $emoji = $this->catalogue->byHexcode($key);

            if ($emoji instanceof Emoji) {
                $tokens[] = new Token($offset, strlen($whole), Mode::Image, $emoji->hexcode);
            }
        }

        return $tokens;
    }
}
