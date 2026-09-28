<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Security;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Text\Token;
use Simtabi\Laranail\Emojis\Core\Text\Scanner;
use Simtabi\Laranail\Emojis\Core\Text\ScanOptions;
use Simtabi\Laranail\Emojis\Core\Enums\Qualification;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmoji;

/**
 * Makes untrusted text safe to store, display and pass on — first the security rules, then an optional
 * emoji policy.
 *
 *     $clean = $emojis->sanitize($input)->clean();
 *     $result = $emojis->sanitize($input)->policy(EmojiPolicy::permissive()->maxEmojis(5))->run();
 *     $result->text;      // cleaned
 *     $result->report;    // what was removed, by kind — never the content
 *
 * The security rules target invisible and reordering characters that hide content from people while
 * machines still read it: bytes smuggled in variation selectors or tag characters (a known way to hide
 * instructions from a human reviewer in text passed to an LLM), Trojan Source bidi controls, zero-width
 * fillers, "Zalgo" combining floods and orphan emoji components. Characters that are legitimate *inside* an
 * emoji sequence (ZWJ, VS16, skin tones, keycap marks, subdivision-flag tags) are kept there, because the
 * scanner has already recognised those sequences as a whole. ZWJ and ZWNJ between letters are kept too —
 * Indic and Persian words need them — and a single variation selector after a character is kept, which
 * leaves Japanese ideographic variation sequences intact.
 *
 * Immutable. Nothing here depends on PCRE emoji properties; letter and mark classes are general categories,
 * which every PCRE build has.
 */
final readonly class Sanitizer
{
    public function __construct(
        private Emojis $emojis,
        private string $text,
        private EmojiPolicy $policy = new EmojiPolicy,
        private int $maxCombiningMarks = 4,
        private bool $removeBidi = true,
    ) {}

    public function policy(EmojiPolicy $policy): self
    {
        return new self($this->emojis, $this->text, $policy, $this->maxCombiningMarks, $this->removeBidi);
    }

    /** How many combining marks one character may carry before the rest are removed (default 4). */
    public function maxCombiningMarks(int $max): self
    {
        return new self($this->emojis, $this->text, $this->policy, max(1, $max), $this->removeBidi);
    }

    /** Keep bidi embeddings, overrides and isolates (for trusted right-to-left authoring tools only). */
    public function keepBidiControls(): self
    {
        return new self($this->emojis, $this->text, $this->policy, $this->maxCombiningMarks, false);
    }

    public function clean(): string
    {
        return $this->run()->text;
    }

    public function report(): SanitizationReport
    {
        return $this->run()->report;
    }

    /** True when the security pass would remove nothing (the policy is not consulted). */
    public function isSafe(): bool
    {
        return new self($this->emojis, $this->text, new EmojiPolicy, $this->maxCombiningMarks, $this->removeBidi)->run()->report->isClean();
    }

    public function run(): SanitizationResult
    {
        $counts = [];
        $text = $this->text;

        if (! mb_check_encoding($text, 'UTF-8')) {
            $scrubbed = mb_scrub($text, 'UTF-8');
            $counts[Threat::InvalidUtf8->value] = 1;
            $text = $scrubbed;
        }

        $limit = $this->emojis->options()->maxInputBytes;

        // Only the first $limit bytes are inspected, so the rest is cut — and the cut is recorded, because
        // text the rules never saw must not read as safe.
        if (strlen($text) > $limit) {
            $text = mb_strcut($text, 0, $limit, 'UTF-8');
            $counts[Threat::Oversized->value] = 1;
        }

        $text = $this->secure($text, $counts);
        $text = $this->applyPolicy($text, $counts);

        ksort($counts);

        return new SanitizationResult($text, new SanitizationReport($counts));
    }

    private function isVariationSelector(int $cp): bool
    {
        return ($cp >= 0xFE00 && $cp <= 0xFE0F) || ($cp >= 0xE0100 && $cp <= 0xE01EF);
    }

    private function isLetter(string $char): bool
    {
        return $char !== '' && preg_match('/^[\p{L}\p{M}\p{N}]$/u', $char) === 1;
    }

    private function isIdeograph(string $char): bool
    {
        return preg_match('/^\p{Han}$/u', $char) === 1;
    }

    /** @param array<string, int> $counts */
    private function secure(string $text, array &$counts): string
    {
        $scanner = $this->emojis->scanner();
        // A component on its own (a skin-tone swatch, a hair style) is not a sequence worth protecting.
        $protected = array_values(array_filter(
            $scanner->scan($text, new ScanOptions([Mode::Unicode], textPresentation: true)),
            static fn (Token $token): bool => $token->qualification !== Qualification::Component,
        ));
        $out = '';
        $cursor = 0;
        // Carried across runs: the last character written, and how many joiners in a row lead up to it. A
        // chain of known emoji joined by ZWJ is a run of single joiners between protected tokens, so the
        // "four in a row" cap only holds if the count survives the token in between.
        $state = ['previous' => '', 'joins' => 0];
        $afterEmoji = false;

        foreach ($protected as $token) {
            $out .= $this->secureRun(substr($text, $cursor, $token->offset - $cursor), $counts, $state, $afterEmoji, true);
            $emoji = $token->text($text);
            $out .= $emoji;
            $state['previous'] = mb_substr($emoji, -1, 1, 'UTF-8');
            $cursor = $token->end();
            $afterEmoji = true;
        }

        return $out . $this->secureRun(substr($text, $cursor), $counts, $state, $afterEmoji, false);
    }

    /**
     * Applies the character rules to a run of text that contains no recognised emoji sequence.
     *
     * @param array<string, int> $counts
     * @param array{previous: string, joins: int} $state the last character written and the joiners in a row
     *                                                   before it, carried across token boundaries
     * @param bool $afterEmoji the run starts right after a recognised emoji
     * @param bool $beforeEmoji the run ends right before a recognised emoji
     */
    private function secureRun(string $run, array &$counts, array &$state, bool $afterEmoji, bool $beforeEmoji): string
    {
        if ($run === '') {
            return '';
        }

        $chars = mb_str_split($run, 1, 'UTF-8');
        $out = '';
        $first = true;
        $marks = 0;
        $variation = false;
        $count = static function (Threat $threat) use (&$counts): void {
            $counts[$threat->value] = ($counts[$threat->value] ?? 0) + 1;
        };

        foreach ($chars as $index => $char) {
            $cp = mb_ord($char, 'UTF-8');
            $previous = $state['previous'];
            $next = $chars[$index + 1] ?? '';

            $threat = match (true) {
                ($cp < 0x20 && ! in_array($cp, [0x09, 0x0A, 0x0D], true)) || ($cp >= 0x7F && $cp <= 0x9F) => Threat::Control,
                $this->removeBidi && $this->isBidi($cp)                                                   => Threat::Bidi,
                $this->isInvisible($cp)                                                                   => Threat::Invisible,
                $cp >= 0xE0000 && $cp <= 0xE007F                                                          => Threat::Tag,
                // At most one selector, and only after a character Unicode defines that selector for: VS15/16
                // after an emoji-capable character, VS1–14 after a standardized-variant base, VS17–256 after a
                // CJK ideograph. Anything else is payload — one hidden byte per visible character adds up.
                $this->isVariationSelector($cp) => $variation || ! $this->takesSelector($cp, $previous) ? Threat::VariationSelector : null,
                // Between letters (Indic, Persian) or between two emoji (a vendor sequence such as 🐱‍👤, or one
                // newer than the dataset) a joiner is meaningful, up to four joins in a row — the longest RGI
                // sequence has three, so more is a renderer-abuse chain. Anywhere else it hides nothing.
                $cp === 0x200C || $cp === 0x200D                                                                                                 => $this->joins($previous, $next, $first && $afterEmoji, $next === '' && $beforeEmoji, $state['joins']) ? null : Threat::Joiner,
                ($cp >= 0x1F3FB && $cp <= 0x1F3FF) || $cp === 0x20E3 || ($cp >= 0x1F1E6 && $cp <= 0x1F1FF) || ($cp >= 0x1F9B0 && $cp <= 0x1F9B3) => Threat::OrphanComponent,
                // UTS #39: no more than maxCombiningMarks on one character, and never the same mark twice running.
                preg_match('/^\p{M}$/u', $char) === 1 => $marks >= $this->maxCombiningMarks || $char === $previous ? Threat::Combining : null,
                default                               => null,
            };

            if ($threat instanceof Threat) {
                $count($threat);

                continue;
            }

            if ($this->isVariationSelector($cp)) {
                $variation = true;
            } elseif (preg_match('/^\p{M}$/u', $char) === 1) {
                $marks++;
            } elseif ($cp === 0x200D || $cp === 0x200C) {
                $state['joins']++;
            } else {
                $marks = 0;
                $variation = false;

                if (! $this->isPictograph($char)) {
                    $state['joins'] = 0;
                }
            }

            $out .= $char;
            $state['previous'] = $char;
            $first = false;
        }

        return $out;
    }

    /** Embeddings, overrides and isolates (Trojan Source), and the implicit direction marks LRM, RLM and ALM. */
    private function isBidi(int $cp): bool
    {
        return ($cp >= 0x202A && $cp <= 0x202E) || ($cp >= 0x2066 && $cp <= 0x2069) || $cp === 0x200E || $cp === 0x200F || $cp === 0x061C;
    }

    /**
     * Characters that render as nothing (or as blank space indistinguishable from a space) and so can hide
     * content: zero-width spaces and invisible operators, the soft hyphen, the combining grapheme joiner,
     * line and paragraph separators, the braille blank, interlinear annotation controls, musical formatting
     * controls, Khmer inherent vowels and the Hangul fillers.
     */
    private function isInvisible(int $cp): bool
    {
        return in_array($cp, [0x00AD, 0x034F, 0x115F, 0x1160, 0x17B4, 0x17B5, 0x180E, 0x200B, 0x2028, 0x2029, 0x2060, 0x2061, 0x2062, 0x2063, 0x2064, 0x2800, 0x3164, 0xFEFF, 0xFFA0], true)
            || ($cp >= 0xFFF9 && $cp <= 0xFFFB)
            || ($cp >= 0x1D173 && $cp <= 0x1D17A);
    }

    /** @param array<string, int> $counts */
    private function applyPolicy(string $text, array &$counts): string
    {
        if ($this->policy->isPermissive()) {
            return $text;
        }

        $scanner = $this->emojis->scanner();
        $known = $scanner->scan($text, new ScanOptions([Mode::Unicode, Mode::Shortcode]));
        $tokens = Scanner::resolveOverlaps([...$known, ...$scanner->pictographs($text, $known)]);
        $catalogue = $this->emojis->catalogue();
        $out = '';
        $cursor = 0;
        $kept = 0;

        foreach ($tokens as $token) {
            $out .= substr($text, $cursor, $token->offset - $cursor);
            $cursor = $token->end();
            $threat = $this->judge($token, $catalogue->byHexcode((string) $token->hexcode) instanceof Emoji);

            if (! $threat instanceof Threat && $this->policy->maxEmojis !== null && $kept >= $this->policy->maxEmojis) {
                $threat = Threat::Limit;
            }

            if ($threat instanceof Threat) {
                $counts[$threat->value] = ($counts[$threat->value] ?? 0) + 1;
                $out .= $this->policy->replacement ?? '';

                continue;
            }

            $kept++;
            $out .= $token->text($text);
        }

        return $out . substr($text, $cursor);
    }

    private function judge(Token $token, bool $known): ?Threat
    {
        if ($token->custom instanceof CustomEmoji) {
            return $this->policy->allowCustom ? null : Threat::Policy;
        }

        if (! $known) {
            return $this->policy->allowUnknown ? null : Threat::Policy;
        }

        $emoji = $this->emojis->catalogue()->byHexcode((string) $token->hexcode);

        return $emoji instanceof Emoji && $this->policy->permits($emoji) ? null : Threat::Policy;
    }

    private function takesSelector(int $selector, string $base): bool
    {
        if ($base === '') {
            return false;
        }

        $data = $this->emojis->dataset();

        return match (true) {
            $selector === 0xFE0E || $selector === 0xFE0F => preg_match('/^[' . $data->emojiVsBaseClass() . $data->pictographicClass() . ']$/u', $base) === 1,
            $selector <= 0xFE0D                          => preg_match('/^[' . $data->standardizedVsBaseClass() . ']$/u', $base) === 1,
            default                                      => $this->isIdeograph($base),
        };
    }

    private function joins(string $previous, string $next, bool $afterEmoji, bool $beforeEmoji, int $joins): bool
    {
        $letters = $this->isLetter($previous) && $this->isLetter($next);
        $emoji = ($afterEmoji || $this->isPictograph($previous)) && ($beforeEmoji || $this->isPictograph($next));

        return $letters || ($emoji && $joins < 4);
    }

    private function isPictograph(string $char): bool
    {
        return $char !== '' && preg_match('/^[' . $this->emojis->dataset()->pictographicClass() . ']$/u', $char) === 1;
    }
}
