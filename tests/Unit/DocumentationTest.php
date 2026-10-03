<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\Carrier;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Catalogue\Query;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Text\TextConverter;

/*
 * The docs are part of the product. The template and footer rules come from the family authoring standard;
 * the outputs quoted in the docs are run here, so a behaviour change that makes a page wrong fails the
 * suite instead of shipping a page that lies.
 */

/** @return array<string, string> relative path => contents */
function docPages(): array
{
    $root = dirname(__DIR__, 2);
    $pages = [];

    foreach ([...glob($root . '/docs/*.md') ?: [], ...glob($root . '/docs/*/*.md') ?: []] as $file) {
        $pages[substr($file, strlen($root) + 1)] = (string) file_get_contents($file);
    }

    return $pages;
}

it('opens every page with its title and ends it with exactly one footer at the right depth', function (): void {
    $pages = docPages();

    expect(count($pages))->toBeGreaterThanOrEqual(22);

    foreach ($pages as $path => $contents) {
        $depth = substr_count($path, '/') === 1 ? '../' : '../../';
        $footer = "[← Docs index]({$depth}README.md#documentation)";

        expect($contents)->toStartWith('# ', $path)
            ->and(substr_count($contents, '← Docs index'))->toBe(1, $path)
            ->and(rtrim($contents))->toEndWith($footer, $path);
    }
});

it('keeps the README spine: Install, then Quick start guide and usage, then Documentation', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__, 2) . '/README.md');
    $at = static fn (string $heading): int|false => strpos($readme, "\n" . $heading . "\n");
    $quick = '## Quick start guide and usage';

    expect($at('## Install'))->toBeInt()
        ->and($at($quick))->toBeGreaterThan($at('## Install'))
        ->and($at('### Getting started'))->toBeGreaterThan($at($quick))
        ->and($at('### Usage'))->toBeGreaterThan($at('### Getting started'))
        ->and($at('## <a name="documentation"></a>Documentation'))->toBeGreaterThan($at('### Usage'))
        ->and($at('## Quick start'))->toBeFalse();
});

it('lists every public converter and query method on its reference page', function (string $class, string $page): void {
    $doc = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $page);
    $inspected = 0;

    foreach (new ReflectionClass($class)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->isConstructor() || str_starts_with($method->name, '__') || str_contains((string) $method->getDocComment(), '@internal')) {
            continue;
        }

        $inspected++;
        expect(preg_match('/[`, ]' . $method->name . '\\(/', $doc))->toBe(1, "{$page} does not list {$method->name}()");
    }

    expect($inspected)->toBeGreaterThan(10);
})->with([
    'converter' => [TextConverter::class, 'docs/tools/conversion.md'],
    'query'     => [Query::class, 'docs/tools/querying.md'],
]);

it('keeps the README index anchor the footers point at', function (): void {
    expect((string) file_get_contents(dirname(__DIR__, 2) . '/README.md'))->toContain('## <a name="documentation"></a>Documentation');
});

it('links only to files that exist', function (): void {
    $root = dirname(__DIR__, 2);
    $checked = 0;

    foreach (['README.md' => (string) file_get_contents($root . '/README.md'), ...docPages()] as $path => $contents) {
        preg_match_all('/\]\((?!https?:|#)([^)#]+)/', $contents, $links);

        foreach ($links[1] as $link) {
            expect(file_exists(dirname($root . '/' . $path) . '/' . $link))->toBeTrue("{$path} → {$link}");
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(40);
});

it('shows outputs the code really produces', function (string $actual, string $documented): void {
    expect($actual)->toBe($documented);
})->with([
    'readme: quick start emoji'                 => fn (): array => [emojis()->text('Ship it :rocket: :)')->withEmoticons()->toEmoji(), 'Ship it 🚀 🙂'],
    'readme: quick start ascii'                 => fn (): array => [emojis()->text('Ship it 🚀')->toAscii(), 'Ship it :rocket:'],
    'getting started: emoticons and shortcodes' => fn (): array => [emojis()->text('Ship it :rocket: :)')->withEmoticons()->toEmoji(), 'Ship it 🚀 🙂'],
    'getting started: ascii'                    => fn (): array => [emojis()->text('Ship it 🚀')->toAscii(), 'Ship it :rocket:'],
    'getting started: name'                     => fn (): array => [emojis()->text('Ship it 🚀')->to(Mode::Name), 'Ship it [rocket]'],
    'getting started: strip'                    => fn (): array => [emojis()->strip('Great work 🎉👏🏽'), 'Great work '],
    'getting started: tone'                     => fn (): array => [(string) emojis()->get('wave')->withSkinTone(SkinTone::Medium), '👋🏽'],
    'catalogue: handshake'                      => fn (): array => [(string) emojis()->get('handshake')->withSkinTone(SkinTone::Light, SkinTone::Dark), '🫱🏻‍🫲🏿'],
    'catalogue: unqualified'                    => fn (): array => [emojis()->get('☺')->hexcode, '263A-FE0F'],
    'catalogue: cldr name'                      => fn (): array => [emojis()->get('man: red hair')->char, '👨‍🦰'],
    'modes: degrade'                            => fn (): array => [emojis()->text('😀')->degrade(Mode::Text, Mode::Name)->toText(), '[grinning face]'],
    'terminal: width'                           => fn (): array => [(string) emojis()->text('Deploy 🚀 done')->width(), '14'],
    'localisation: names'                       => fn (): array => [emojis()->text('🚀 👋🏽')->locale('fr')->toNames(), '[fusée] [signe de la main: peau légèrement mate]'],
    'recipe: console'                           => fn (): array => [emojis()->text(':white_check_mark: Deployed :rocket:')->to(Mode::Emoji), '✅ Deployed 🚀'],
    'recipe: utf8mb3'                           => fn (): array => [emojis()->text('Coffee first ☕🚀')->toAscii(), 'Coffee first :coffee::rocket:'],
    'images: fitted OpenMoji viewBox'           => fn (): array => [(string) preg_replace('/^.*?(viewBox="[^"]+").*$/s', '$1', emojis()->text('😀')->imageSet('openmoji')->toImages()), 'viewBox="95 95 810 810"'],
    'japanese: carrier sun'                     => fn (): array => [emojis()->text(mb_chr(0xE63E, 'UTF-8'))->carrier(Carrier::Docomo)->from(Mode::Carrier)->toEmoji(), '☀️'],
    'japanese: name'                            => fn (): array => [emojis()->get('🈁')->name('ja'), 'ココのマーク'],
    'japanese: search'                          => fn (): array => [(string) emojis()->search('寿司', 'ja')->first(), '🍣'],
    'japanese: collection size'                 => fn (): array => [(string) emojis()->collection('japanese')->count(), '55'],
    'kaomoji: total'                            => fn (): array => [(string) count(emojis()->kaomoji()), '2092'],
    'emoticons: plain'                          => fn (): array => [emojis()->text('Nice one :] ^^')->withEmoticons()->toEmoji(), 'Nice one 🙂 😊'],
    'emoticons: risky'                          => fn (): array => [emojis()->text('o_O T_T')->withEmoticons(risky: true)->toEmoji(), '🤨 😭'],
    'emoticons: lookup'                         => fn (): array => [(string) emojis()->fromEmoticon('>_<'), '😣'],
    'emoticons: of an emoji'                    => fn (): array => [implode(' ', emojis()->get('🙂')->emoticons()), '(: :) :-) :-] :] =]'],
    // These two change the instance, so each gets its own rather than the suite's shared one.
    'emoticons: add one opt-in' => fn (): array => [Emojis::create()->addEmoticon('(y)', 'thumbs up')->text('ok (y) (n)')->withEmoticons()->toEmoji(), 'ok 👍 (n)'],
    'emoticons: remove one'     => fn (): array => [Emojis::create()->removeEmoticon(':)')->text('🙂')->toEmoticons(), ':-)'],
    'recipe: older platforms'   => fn (): array => [emojis()->text('❤️‍🔥 🫩 🚀')->supportedUpTo(EmojiVersion::V13_0)->toEmoji(), '❤️🔥 :face_with_eye_bags: 🚀'],
]);

it('names the current release line wherever a version line is written', function (): void {
    $root = dirname(__DIR__, 2);
    preg_match('/^## \[(\d+)\.(\d+)\.\d+\]/m', (string) file_get_contents($root . '/CHANGELOG.md'), $newest);

    // Before 1.0 a caret constraint stops at the minor version, so the line is "0.3"; from 1.0 it is "1".
    $line = $newest[1] === '0' ? "0.{$newest[2]}" : $newest[1];
    $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);

    expect($newest)->not->toBeEmpty()
        ->and($composer['extra']['branch-alias']['dev-main'] ?? null)->toBe("{$line}.x-dev");

    foreach (['README.md', 'docs/installation.md', 'docs/release.md'] as $page) {
        preg_match_all('/laranail\/emojis:\^([\d.]+)/', (string) file_get_contents("{$root}/{$page}"), $constraints);

        expect($constraints[1])->not->toBeEmpty($page)
            ->and(array_unique($constraints[1]))->toBe([$line], $page);
    }
});

it('documents only custom emoji names that addCustom() accepts', function (): void {
    $names = [];

    foreach (docPages() as $path => $contents) {
        preg_match_all("/addCustom\\('([^']+)'([^\\n]*)/", $contents, $calls, PREG_SET_ORDER);

        foreach ($calls as $call) {
            $names[$call[1]] = $path;
            preg_match('/aliases: \\[([^\\]]*)\\]/', $call[2], $list);
            preg_match_all("/'([^']+)'/", $list[1] ?? '', $aliases);

            foreach ($aliases[1] as $alias) {
                $names[$alias] = $path;
            }
        }
    }

    // A name that is already a shortcode throws InvalidCustomEmoji, so the example would fail as written.
    expect(count($names))->toBeGreaterThanOrEqual(6);

    foreach ($names as $name => $path) {
        expect(emojis()->catalogue()->byShortcode($name))->toBeNull("{$path}: '{$name}' is already a shortcode");
    }
});
