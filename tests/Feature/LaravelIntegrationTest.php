<?php

declare(strict_types=1);

use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Illuminate\Support\Facades\Validator;

use function Simtabi\Laranail\Emojis\emoji;

use Simtabi\Laranail\Emojis\Tests\TestCase;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Laravel\Casts\AsEmoji;
use Simtabi\Laranail\Emojis\Laravel\Rules\NoEmoji;
use Simtabi\Laranail\Emojis\Laravel\Rules\MaxEmojis;
use Simtabi\Laranail\Emojis\Laravel\Rules\OnlyEmoji;
use Simtabi\Laranail\Emojis\Core\Security\EmojiPolicy;
use Simtabi\Laranail\Emojis\Laravel\Casts\AsEmojiText;
use Simtabi\Laranail\Emojis\Laravel\Rules\SingleEmoji;
use Simtabi\Laranail\Emojis\Core\Contracts\EmojisFluent;
use Simtabi\Laranail\Emojis\Laravel\Rules\ContainsEmoji;
use Simtabi\Laranail\Emojis\Core\Contracts\TerminalProbe;
use Simtabi\Laranail\Emojis\Laravel\Rules\EmojiPolicyRule;
use Simtabi\Laranail\Emojis\Core\Terminal\EnvTerminalProbe;
use Simtabi\Laranail\Emojis\Facades\Emojis as EmojisFacade;
use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;
use Simtabi\Laranail\Emojis\Laravel\Rules\NoHiddenCharacters;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidCustomEmoji;

it('binds one instance behind the class, the contract, the facade and the helper', function (): void {
    expect(app(Emojis::class))->toBe(app(EmojisFluent::class))
        ->and(EmojisFacade::getFacadeRoot())->toBe(app(Emojis::class))
        ->and(emoji())->toBe(app(Emojis::class))
        ->and((string) emoji('wave'))->toBe('👋')
        ->and(EmojisFacade::text(':rocket:')->toEmoji())->toBe('🚀');
});

it('returns HtmlString so Blade does not escape images twice', function (): void {
    $html = app(Emojis::class)->text('<b> 🚀')->toHtml();

    expect($html)->toBeInstanceOf(HtmlString::class)
        ->and(Blade::render('{{ $html }}', ['html' => $html]))->toContain('&lt;b&gt; <img ');
});

it('renders the Blade component accessibly', function (): void {
    expect(Blade::render('<x-laranail-emojis::emoji name="wave" skin-tone="medium" />'))->toBe('<span class="laranail-emoji laranail-emoji-native" role="img" aria-label="waving hand: medium skin tone">👋🏽</span>')
        ->and(Blade::render('<x-laranail-emojis::emoji name="rocket" mode="image" set="openmoji" />'))->toContain('openmoji@17.0.0/color/svg/1F680.svg');
});

it('honours locale in every Blade component mode, not just images', function (): void {
    expect(Blade::render('<x-laranail-emojis::emoji name="rocket" mode="name" locale="fr" />'))->toBe('<span class="laranail-emoji laranail-emoji-native" role="img" aria-label="fusée">[fusée]</span>');
});

it('names the accepted values when a Blade component mode or fit is unknown', function (string $tag, string $message): void {
    expect(static fn (): string => Blade::render($tag))->toThrow(Exception::class, $message);
})->with([
    'mode' => ['<x-laranail-emojis::emoji name="rocket" mode="emojii" />', 'Unknown mode "emojii"; expected one of: '],
    'fit'  => ['<x-laranail-emojis::emoji name="rocket" mode="image" fit="snug" />', 'Unknown fit "snug"; expected one of: '],
]);

it('follows the application locale on every call', function (): void {
    app()->setLocale('fr');
    expect(app(Emojis::class)->get('rocket')->name())->toBe('fusée');

    app()->setLocale('de');
    expect(app(Emojis::class)->get('rocket')->name())->toBe('Rakete');
});

it('labels an image in the application locale of the call, not the one at boot', function (): void {
    app()->setLocale('fr');

    expect((string) app(Emojis::class)->get('rocket')->toImage())->toContain('alt="🚀"')->toContain('fusée');
});

it('warns once about an unshipped application locale, however many emoji a search ranks', function (): void {
    $warnings = 0;
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$warnings): void {
        if (str_contains($event->message, 'locale-not-shipped')) {
            $warnings++;
        }
    });

    app()->setLocale('tlh');
    app(Emojis::class)->search('face');
    app(Emojis::class)->search('heart');

    expect($warnings)->toBe(1);
});

it('validates with the emoji rules', function (mixed $value, object $rule, bool $passes): void {
    expect(Validator::make(['v' => $value], ['v' => [$rule]])->passes())->toBe($passes);
})->with([
    'no emoji, clean'          => ['hello', new NoEmoji, true],
    'no emoji, dirty'          => ['hello 👋', new NoEmoji, false],
    'no emoji, stray modifier' => ["x \u{1F3FD}", new NoEmoji, false],
    'no emoji, non-string'     => [['a'], new NoEmoji, false],
    'contains'                 => ['hi 🚀', new ContainsEmoji, true],
    'contains, none'           => ['hi', new ContainsEmoji, false],
    'only'                     => [' 🚀👋 ', new OnlyEmoji, true],
    'only, with text'          => ['🚀 go', new OnlyEmoji, false],
    'single'                   => ['👍🏽', new SingleEmoji, true],
    'single, two'              => ['👍👍', new SingleEmoji, false],
    'max 2, three'             => ['😀😀😀', new MaxEmojis(2), false],
    'max 2, two'               => ['😀 x 😀', new MaxEmojis(2), true],
]);

it('translates rule messages from its own namespace', function (): void {
    $errors = Validator::make(['bio' => 'hi 🚀'], ['bio' => [new NoEmoji]])->errors();

    expect($errors->first('bio'))->toBe('The bio field must not contain emoji.');
});

it('casts one emoji to its hexcode and free text to shortcodes', function (): void {
    $model = new class extends Model {};

    expect((new AsEmoji)->set($model, 'r', '👋🏽', []))->toBe('1F44B-1F3FD')
        ->and((string) (new AsEmoji)->get($model, 'r', '1F44B-1F3FD', []))->toBe('👋🏽')
        ->and((new AsEmojiText)->set($model, 'bio', 'Ship it 🚀', []))->toBe('Ship it :rocket:')
        ->and((new AsEmojiText)->get($model, 'bio', 'Ship it :rocket:', []))->toBe('Ship it 🚀');
});

it('boots healthy', function (): void {
    expect(app(BootReport::class)->isHealthy())->toBeTrue(json_encode(app(BootReport::class)->degraded()) ?: '');
});

it('reads config only at its registered key', function (): void {
    $this->assertReadsConfigAtRegisteredKey(dirname(__DIR__, 2) . '/src', 'emojis');
});

it('freezes registrations once the application has booted', function (): void {
    app(Emojis::class)->addShortcode('shipit', 'rocket');
})->throws(InvalidCustomEmoji::class);

it('runs its commands', function (): void {
    $this->artisan('laranail::emojis.convert', ['text' => 'Hi :) 🚀', '--to' => 'ascii', '--from' => 'unicode,emoticon'])
        ->expectsOutput('Hi :slightly_smiling_face: :rocket:')
        ->assertSuccessful();

    $this->artisan('laranail::emojis.show', ['emoji' => 'nope-nope'])->assertFailed();
    $this->artisan('laranail::emojis.search', ['term' => 'rocket'])->assertSuccessful();
    $this->artisan('laranail::emojis.convert', ['text' => 'x', '--to' => 'bogus'])->assertExitCode(2);
});

it('exports a versioned JSON catalogue', function (): void {
    $path = sys_get_temp_dir() . '/laranail-emojis-export-' . bin2hex(random_bytes(4)) . '.json';

    try {
        $this->artisan('laranail::emojis.export', ['path' => $path, '--locale' => 'fr'])->assertSuccessful();
        $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        expect($document['schemaVersion'])->toBe(1)
            ->and($document['locale'])->toBe('fr')
            ->and(count($document['emojis']))->toBeGreaterThan(1800)
            ->and($document['emojis'][0])->toHaveKeys(['emoji', 'hexcode', 'name', 'keywords', 'shortcodes', 'skins']);
    } finally {
        @unlink($path);
    }
});

it('resolves its collaborators from the container, so an application can replace them', function (): void {
    app()->forgetInstance(Emojis::class);
    app()->singleton(TerminalProbe::class, static fn (): EnvTerminalProbe => new EnvTerminalProbe(override: false));

    expect(app(Emojis::class)->text('🚀')->to(Mode::Auto))->toBe(':rocket:');
});

it('rejects hidden characters and enforces the emoji policy', function (): void {
    $tags = implode('', array_map(static fn (string $c): string => mb_chr(0xE0000 + ord($c), 'UTF-8'), str_split('hi')));

    expect(Validator::make(['v' => 'plain name'], ['v' => [new NoHiddenCharacters]])->passes())->toBeTrue()
        ->and(Validator::make(['v' => 'name' . $tags], ['v' => [new NoHiddenCharacters]])->passes())->toBeFalse()
        ->and(Validator::make(['v' => '👍'], ['v' => [new EmojiPolicyRule(EmojiPolicy::only('1F44D'))]])->passes())->toBeTrue()
        ->and(Validator::make(['v' => '🚀'], ['v' => [new EmojiPolicyRule(EmojiPolicy::only('1F44D'))]])->errors()->first('v'))->toBe('The v field contains emoji that are not allowed here.');
});

it('reads the default policy from config', function (): void {
    config()->set('laranail.emojis.policy', ['deny_groups' => ['flags']]);
    app()->forgetInstance(Emojis::class);

    expect(app(Emojis::class)->sanitize('go 🇰🇪 🚀')->clean())->toBe('go  🚀');
});

it('sanitizes from the command line and gates with --check', function (): void {
    $this->artisan('laranail::emojis.sanitize', ['text' => "ok \u{202E}x"])->expectsOutput('ok x')->assertSuccessful();
    $this->artisan('laranail::emojis.sanitize', ['text' => "ok \u{202E}x", '--check' => true])->assertFailed();
    $this->artisan('laranail::emojis.sanitize', ['text' => 'clean 🚀', '--check' => true])->assertSuccessful();
});

it('inlines the stylesheet with a nonce', function (): void {
    $html = Blade::render('<x-laranail-emojis::styles nonce="abc123" />');

    expect($html)->toStartWith('<style nonce="abc123">')
        ->and($html)->toContain('.laranail-emoji-box')
        ->and(Blade::render('<x-laranail-emojis::styles />'))->toStartWith('<style>');
});

it('links the published stylesheet instead of inlining it', function (): void {
    $html = Blade::render('<x-laranail-emojis::styles link nonce="abc123" />');

    expect($html)->toBe('<link rel="stylesheet" href="' . asset('vendor/laranail/emojis/css/emojis.css') . '" nonce="abc123">');
});

it('publishes the built assets to public/vendor/laranail/emojis', function (): void {
    $paths = ServiceProvider::pathsToPublish(null, 'laranail::emojis-assets');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('public/assets')
        ->and(array_values($paths)[0])->toBe(public_path('vendor/laranail/emojis'))
        ->and(is_file(array_key_first($paths) . '/css/emojis.css'))->toBeTrue();
});

it('applies added and disabled emoticons from config at boot', function (): void {
    TestCase::$bootConfig = [
        'laranail.emojis.extend.emoticons'         => ['(y)' => '1F44D', ':X' => '1F910'],
        'laranail.emojis.input.disabled_emoticons' => [':P'],
    ];

    try {
        $this->refreshApplication();
        $emojis = app(Emojis::class);

        expect($emojis->text('ok (y) (n) :X :P')->withEmoticons()->toEmoji())->toBe('ok 👍 (n) 🤐 :P')
            ->and($emojis->fromEmoticon(':P'))->toBeNull();
    } finally {
        TestCase::$bootConfig = [];
    }
});
