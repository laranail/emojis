<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\Carrier;

it('never converts inside a quoted attribute holding a ">"', function (string $html): void {
    expect(emojis()->html($html)->toImages())->toBe($html);
})->with([
    'double quotes' => '<a title="x> :wave: y">t</a>',
    'single quotes' => "<a title='x> :wave: y'>t</a>",
]);

it('still converts the text after a tag whose attribute holds a ">"', function (): void {
    expect(emojis()->html('<a title="x> y">:wave:</a>')->toEmoji())->toBe('<a title="x> y">👋</a>');
});

it('treats <code/> as an opening tag, as HTML does', function (): void {
    expect(emojis()->html('<code/>:wave:</code>')->toEmoji())->toBe('<code/>:wave:</code>');
});

it('does not parse tags inside raw-text elements', function (string $html, string $expected): void {
    expect(emojis()->html($html)->toEmoji())->toBe($expected);
})->with([
    'script holding "<code>"' => ['<script>var s="<code>";</script><p>:wave:</p>', '<script>var s="<code>";</script><p>👋</p>'],
    'style holding "<pre>"'   => ['<style>/* <pre> */</style>:wave:', '<style>/* <pre> */</style>👋'],
    'script holding :wave:'   => ['<script>if (a < b) { s = ":wave:"; }</script>', '<script>if (a < b) { s = ":wave:"; }</script>'],
    'textarea holding tags'   => ['<textarea><b>:wave:</b></textarea>:wave:', '<textarea><b>:wave:</b></textarea>👋'],
    'upper-case closing tag'  => ['<SCRIPT>"<code>"</SCRIPT>:wave:', '<SCRIPT>"<code>"</SCRIPT>👋'],
]);

it('writes HTML entities into HTML once, not escaped again', function (): void {
    expect(emojis()->html('<p>:wave:</p>')->toHtmlEntities())->toBe('<p>&#x1F44B;</p>')
        ->and((string) emojis()->text(':wave: <b>')->toHtml(Mode::HtmlEntity))->toBe('&#x1F44B; &lt;b&gt;');
});

it('keeps plain-text HTML entity output unescaped', function (): void {
    expect(emojis()->text(':wave: <b>')->toHtmlEntities())->toBe('&#x1F44B; <b>');
});

it('reads a named carrier inside HTML', function (): void {
    $sun = mb_chr(0xE63E, 'UTF-8');

    expect(emojis()->html('<p>' . $sun . '</p>')->carrier(Carrier::Docomo)->from(Mode::Carrier)->toEmoji())->toBe('<p>☀️</p>');
});
