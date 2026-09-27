<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Text;

use Closure;
use Stringable;

use function grapheme_strlen;
use function grapheme_str_split;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Fit;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Render\Piece;
use Simtabi\Laranail\Emojis\Core\Enums\Carrier;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Enums\EscapeFormat;
use Simtabi\Laranail\Emojis\Core\Enums\ShortcodePreset;
use Simtabi\Laranail\Emojis\Core\Extension\CustomEmoji;
use Simtabi\Laranail\Emojis\Core\Render\RenderSettings;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidInput;
use Simtabi\Laranail\Emojis\Core\Catalogue\EmojiCollection;

/**
 * Converts every emoji in a piece of text from the forms it is written in to one target form.
 *
 *     $emojis->text('Ship it :rocket: :)')->withEmoticons()->to(Mode::Emoji);   // "Ship it 🚀 🙂"
 *     $emojis->text('Ship it 🚀')->to(Mode::Ascii);                             // "Ship it :rocket:"
 *     $emojis->html('<p>hi :wave:</p><code>:wave:</code>')->toImages();         // code left alone
 *
 * Immutable: every option returns a new converter, so one configured converter can be reused.
 *
 * Plain text in, HTML out (Image mode, toHtml()) escapes the text around the emoji. HTML in converts text
 * runs only (see HtmlSegments) and leaves markup byte-identical.
 */
final readonly class TextConverter implements Stringable
{
    /**
     * @param list<Mode> $sources
     * @param array<string, list<Mode>> $chains
     * @param list<Closure(string, ?Emoji, ?CustomEmoji): string> $stages
     */
    public function __construct(
        private Emojis $emojis,
        private string $text,
        private bool $isHtml = false,
        private array $sources = [Mode::Unicode, Mode::Shortcode],
        private bool $riskyEmoticons = false,
        private bool $textPresentation = false,
        private ?ShortcodePreset $preset = null,
        private ?string $locale = null,
        private ?string $imageSet = null,
        private ?EmojiVersion $versionCap = null,
        private bool $strict = false,
        private ?SkinTone $skinTone = null,
        private array $chains = [],
        private array $stages = [],
        private Carrier $carrier = Carrier::Google,
        private ?Fit $fit = null,
    ) {
        if (! mb_check_encoding($text, 'UTF-8')) {
            throw InvalidInput::invalidUtf8();
        }

        $limit = $emojis->options()->maxInputBytes;

        if (strlen($text) > $limit) {
            throw InvalidInput::tooLarge(strlen($text), $limit);
        }
    }

    public function __toString(): string
    {
        return $this->to(Mode::Emoji);
    }

    // ---- what to read ------------------------------------------------------------------------

    /** Replace the source forms to parse (default: Unicode and Shortcode). */
    public function from(Mode ...$sources): self
    {
        return $this->with(['sources' => $this->uniqueModes($sources)]);
    }

    /** Also parse forms, keeping the current ones. */
    public function alsoFrom(Mode ...$sources): self
    {
        return $this->from(...$this->sources, ...$sources);
    }

    /** Parse ASCII emoticons (":)", "<3"). Word-bounded; "risky" ones ("XD", "D:", "8)") only when asked. */
    public function withEmoticons(bool $risky = false): self
    {
        return $this->with(['sources' => $this->uniqueModes([...$this->sources, Mode::Emoticon]), 'riskyEmoticons' => $risky]);
    }

    /**
     * Read and write a Japanese carrier's private-use emoji. Reading needs the carrier named because docomo,
     * au and SoftBank overlap: `->carrier(Carrier::Docomo)->from(Mode::Carrier)->toEmoji()`.
     */
    public function carrier(Carrier $carrier): self
    {
        return $this->with(['carrier' => $carrier]);
    }

    /** Also treat text-default characters without FE0F (©, ®, ™, ☺) as emoji. */
    public function includeTextPresentation(bool $include = true): self
    {
        return $this->with(['textPresentation' => $include]);
    }

    // ---- how to write -------------------------------------------------------------------------

    public function preset(ShortcodePreset $preset): self
    {
        return $this->with(['preset' => $preset]);
    }

    public function locale(string $locale): self
    {
        return $this->with(['locale' => $locale]);
    }

    public function imageSet(string $set): self
    {
        return $this->with(['imageSet' => $set]);
    }

    /** How images fill their box: Fit::None (published padding), Balanced (default), Tight. */
    public function fit(Fit $fit): self
    {
        return $this->with(['fit' => $fit]);
    }

    /** Render as a platform supporting only this Emoji version would: newer emoji decompose or degrade. */
    public function supportedUpTo(EmojiVersion $version): self
    {
        return $this->with(['versionCap' => $version]);
    }

    /** Apply a skin tone to every single-person emoji that takes one and has none. */
    public function skinTone(?SkinTone $tone): self
    {
        return $this->with(['skinTone' => $tone]);
    }

    /** Throw UnsupportedConversion instead of degrading when the target cannot represent an emoji. */
    public function strict(bool $strict = true): self
    {
        return $this->with(['strict' => $strict]);
    }

    /** Override the fallback chain for a target: degrade(Mode::Text, Mode::Shortcode, Mode::Ascii). */
    public function degrade(Mode $target, Mode ...$chain): self
    {
        return $this->with(['chains' => [...$this->chains, $target->value => array_values($chain)]]);
    }

    /**
     * Post-process each rendered emoji: fn (string $rendered, ?Emoji $emoji, ?CustomEmoji $custom): string.
     * Stages run in registration order, each receiving the previous stage's output.
     *
     * @param Closure(string, ?Emoji, ?CustomEmoji): string $stage
     */
    public function through(Closure $stage): self
    {
        return $this->with(['stages' => [...$this->stages, $stage]]);
    }

    // ---- conversion ---------------------------------------------------------------------------

    /**
     * Convert to a target. Image output (and any HTML input) is HTML; everything else is plain text.
     * Use toHtml() when the result goes into a template, so it is typed as already-escaped HTML.
     */
    public function to(Mode $target, ?EscapeFormat $format = null): string
    {
        $target = $target === Mode::Auto ? $this->emojis->resolveAuto() : $target;
        $settings = $this->settings($format);

        if ($this->isHtml) {
            $out = '';

            foreach (HtmlSegments::split($this->text) as [$segment, $convertible]) {
                $out .= $convertible ? $this->convertHtmlText($segment, $target, $settings) : $segment;
            }

            return $out;
        }

        return $this->assemble($this->text, $this->tokens(), $target, $settings, escapeText: $target->producesHtml());
    }

    /** The conversion as HTML-safe markup: plain text is escaped, HTML input is converted in place. */
    public function toHtml(Mode $target = Mode::Image, ?EscapeFormat $format = null): Stringable
    {
        if ($this->isHtml || $target->producesHtml()) {
            return $this->emojis->htmlFactory()->make($this->to($target, $format));
        }

        return $this->emojis->htmlFactory()->make(htmlspecialchars($this->to($target, $format), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));
    }

    public function toEmoji(): string
    {
        return $this->to(Mode::Emoji);
    }

    public function toText(): string
    {
        return $this->to(Mode::Text);
    }

    public function toUnicode(): string
    {
        return $this->to(Mode::Unicode);
    }

    public function toAscii(): string
    {
        return $this->to(Mode::Ascii);
    }

    public function toShortcodes(): string
    {
        return $this->to(Mode::Shortcode);
    }

    public function toEmoticons(): string
    {
        return $this->to(Mode::Emoticon);
    }

    public function toImages(): string
    {
        return $this->to(Mode::Image);
    }

    public function toHtmlEntities(): string
    {
        return $this->to(Mode::HtmlEntity);
    }

    public function toEscaped(EscapeFormat $format = EscapeFormat::Php): string
    {
        return $this->to(Mode::Escaped, $format);
    }

    public function toCodepoints(): string
    {
        return $this->to(Mode::Codepoint);
    }

    public function toNames(): string
    {
        return $this->to(Mode::Name);
    }

    /** Every emoji written in its fully-qualified form (keyboard-correct): "☺" + "\u{FE0F}", etc. */
    public function normalize(): string
    {
        return $this->from(Mode::Unicode)->to(Mode::Emoji);
    }

    /**
     * Replace each emoji with whatever the callback returns: fn (Emoji|CustomEmoji $emoji, string $original).
     *
     * @param Closure(Emoji|CustomEmoji, string): string $callback
     */
    public function replace(Closure $callback): string
    {
        $out = '';
        $cursor = 0;

        foreach ($this->tokens() as $token) {
            $subject = $token->custom ?? $this->emojis->catalogue()->byHexcode((string) $token->hexcode);
            $out .= substr($this->text, $cursor, $token->offset - $cursor);
            $out .= $subject === null ? $token->text($this->text) : $callback($subject, $token->text($this->text));
            $cursor = $token->end();
        }

        return $out . substr($this->text, $cursor);
    }

    // ---- inspection ---------------------------------------------------------------------------

    /**
     * Remove every emoji — the known ones in every source form being parsed, plus pictographs the dataset
     * does not know and stray modifiers, variation selectors and tags. For sanitising user input.
     */
    public function strip(bool $collapseWhitespace = false): string
    {
        $out = '';
        $cursor = 0;

        foreach ($this->allTokens(withJoiners: true) as $token) {
            $out .= substr($this->text, $cursor, $token->offset - $cursor);
            $cursor = $token->end();
        }

        $out .= substr($this->text, $cursor);

        return $collapseWhitespace ? trim((string) preg_replace('/[ \t]{2,}/', ' ', $out)) : $out;
    }

    /** @return list<EmojiMatch> */
    public function extract(bool $includeUnknown = false): array
    {
        $catalogue = $this->emojis->catalogue();

        return array_map(fn (Token $t): EmojiMatch => new EmojiMatch(
            $t->offset,
            $t->length,
            $t->text($this->text),
            $t->source,
            $t->hexcode === null ? null : $catalogue->byHexcode($t->hexcode),
            $t->custom,
            $this->text,
        ), $includeUnknown ? $this->allTokens() : $this->tokens());
    }

    /** The catalogue emoji found, in order, duplicates kept. ->unique() for distinct. */
    public function emojis(): EmojiCollection
    {
        $catalogue = $this->emojis->catalogue();
        $found = [];

        foreach ($this->tokens() as $token) {
            $emoji = $token->hexcode === null ? null : $catalogue->byHexcode($token->hexcode);

            if ($emoji instanceof Emoji) {
                $found[] = $emoji;
            }
        }

        return new EmojiCollection($found);
    }

    public function count(bool $includeUnknown = false): int
    {
        return count($includeUnknown ? $this->allTokens() : $this->tokens());
    }

    public function contains(bool $includeUnknown = true): bool
    {
        return $this->count($includeUnknown) > 0;
    }

    /** True when the text is one or more emoji and nothing else but whitespace. */
    public function isOnlyEmoji(): bool
    {
        return $this->count(true) > 0 && trim($this->strip()) === '';
    }

    /** User-perceived characters, counting each emoji sequence as one whatever the ICU version. */
    public function length(): int
    {
        $length = 0;

        foreach ($this->segments() as [$segment, $isEmoji]) {
            $length += $isEmoji ? 1 : $this->graphemes($segment);
        }

        return $length;
    }

    /** Terminal columns: two per emoji sequence, East Asian width for everything else. */
    public function width(): int
    {
        $width = 0;

        foreach ($this->segments() as [$segment, $isEmoji]) {
            $width += $isEmoji ? 2 : mb_strwidth($segment, 'UTF-8');
        }

        return $width;
    }

    /** Cut to at most $width columns without splitting an emoji sequence or a grapheme. */
    public function truncate(int $width, string $ellipsis = '…'): string
    {
        if ($this->width() <= $width) {
            return $this->text;
        }

        $budget = max(0, $width - mb_strwidth($ellipsis, 'UTF-8'));
        $out = '';

        foreach ($this->segments() as [$segment, $isEmoji]) {
            foreach ($isEmoji ? [$segment] : $this->splitGraphemes($segment) as $unit) {
                $cost = $isEmoji ? 2 : mb_strwidth($unit, 'UTF-8');

                if ($cost > $budget) {
                    return $out . $ellipsis;
                }

                $budget -= $cost;
                $out .= $unit;
            }
        }

        return $out . $ellipsis;
    }

    /**
     * @param array<array-key, Mode> $modes
     *
     * @return list<Mode>
     */
    private function uniqueModes(array $modes): array
    {
        $unique = [];

        foreach ($modes as $mode) {
            $unique[$mode->value] = $mode;
        }

        return array_values($unique);
    }

    private function graphemes(string $text): int
    {
        if (function_exists('grapheme_strlen')) {
            $length = grapheme_strlen($text);

            if (is_int($length)) {
                return $length;
            }
        }

        return mb_strlen($text, 'UTF-8');
    }

    /** @return list<string> */
    private function splitGraphemes(string $text): array
    {
        if (function_exists('grapheme_str_split')) {
            $parts = grapheme_str_split($text);

            if (is_array($parts)) {
                return array_values(array_filter($parts, is_string(...)));
            }
        }

        return mb_str_split($text, 1, 'UTF-8');
    }

    // ---- internals ------------------------------------------------------------------------------

    /** @return list<Token> */
    private function tokens(): array
    {
        return $this->emojis->scanner()->scan($this->text, new ScanOptions($this->sources, $this->riskyEmoticons, $this->textPresentation, $this->carrier));
    }

    /**
     * Known tokens plus unknown pictographs, in order — and, for strip(), any zero-width joiner touching one of them — so
     * stripping a vendor sequence such as 🐱‍👤 (two known emoji joined by a ZWJ) leaves no invisible
     * character behind, while a ZWJ inside a word of an Indic or Persian script is left alone.
     *
     * @return list<Token>
     */
    private function allTokens(bool $withJoiners = false): array
    {
        $known = $this->tokens();
        $tokens = Scanner::resolveOverlaps([...$known, ...$this->emojis->scanner()->pictographs($this->text, $known)]);

        if (! $withJoiners) {
            return $tokens;
        }

        $edges = [];

        foreach ($tokens as $token) {
            $edges[$token->offset] = true;
            $edges[$token->end()] = true;
        }

        $offset = 0;

        while (($offset = strpos($this->text, "\u{200D}", $offset)) !== false) {
            if (isset($edges[$offset]) || isset($edges[$offset + 3])) {
                $tokens[] = new Token($offset, 3, Mode::Unicode);
            }

            $offset += 3;
        }

        return Scanner::resolveOverlaps($tokens);
    }

    /** @return list<array{0: string, 1: bool}> */
    private function segments(): array
    {
        $segments = [];
        $cursor = 0;

        foreach ($this->allTokens() as $token) {
            if ($token->offset > $cursor) {
                $segments[] = [substr($this->text, $cursor, $token->offset - $cursor), false];
            }

            $segments[] = [$token->text($this->text), true];
            $cursor = $token->end();
        }

        if ($cursor < strlen($this->text)) {
            $segments[] = [substr($this->text, $cursor), false];
        }

        return $segments;
    }

    /** @param list<Token> $tokens */
    private function assemble(string $text, array $tokens, Mode $target, RenderSettings $settings, bool $escapeText): string
    {
        $renderer = $this->emojis->renderer();
        $escape = static fn (string $s): string => $escapeText ? htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5) : $s;
        $out = '';
        $cursor = 0;

        foreach ($tokens as $token) {
            $out .= $escape(substr($text, $cursor, $token->offset - $cursor));
            $piece = $renderer->render($token, $token->text($text), $target, $settings);
            $piece = $this->applyStages($piece, $token);
            $out .= $piece->html || ! $escapeText ? $piece->text : $escape($piece->text);
            $cursor = $token->end();
        }

        return $out . $escape(substr($text, $cursor));
    }

    private function convertHtmlText(string $segment, Mode $target, RenderSettings $settings): string
    {
        $decoded = html_entity_decode($segment, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $tokens = $this->emojis->scanner()->scan($decoded, new ScanOptions(array_values(array_filter($this->sources, static fn (Mode $m): bool => $m !== Mode::HtmlEntity && $m !== Mode::Image)), $this->riskyEmoticons, $this->textPresentation));

        return $tokens === [] ? $segment : $this->assemble($decoded, $tokens, $target, $settings, escapeText: true);
    }

    private function applyStages(Piece $piece, Token $token): Piece
    {
        if ($this->stages === []) {
            return $piece;
        }

        $emoji = $token->hexcode === null ? null : $this->emojis->catalogue()->byHexcode($token->hexcode);
        $text = $piece->text;

        foreach ($this->stages as $stage) {
            $text = $stage($text, $emoji, $token->custom);
        }

        return new Piece($text, $piece->html);
    }

    private function settings(?EscapeFormat $format): RenderSettings
    {
        return new RenderSettings(
            preset: $this->preset ?? $this->emojis->options()->preset,
            locale: $this->locale,
            imageSet: $this->imageSet,
            versionCap: $this->versionCap,
            strict: $this->strict,
            escapeFormat: $format ?? EscapeFormat::Php,
            skinTone: $this->skinTone,
            chains: $this->chains,
            carrier: $this->carrier,
            fit: $this->fit,
        );
    }

    /** @param array<string, mixed> $changes */
    private function with(array $changes): self
    {
        /** @phpstan-ignore argument.type (named-argument spread of this class's own state) */
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
