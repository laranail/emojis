<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Laravel\BladeDirective;
use Simtabi\Laranail\Emojis\Laravel\Casts\AsEmojiText;
use Simtabi\Laranail\Emojis\Laravel\Doctor\DatasetCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorStatus;
use Simtabi\Laranail\Emojis\Laravel\View\Components\Emoji as EmojiComponent;

// These exercise src/Laravel, so they boot the application like the Feature suite does.

it('renders HTML entities from the Blade directive without escaping them again', function (): void {
    expect((string) BladeDirective::render('hi :wave: <b>', 'html_entity'))->toBe('hi &#x1F44B; &lt;b&gt;');
});

it('renders HTML entities from the component without escaping them again', function (): void {
    $html = (string) new EmojiComponent(app(Emojis::class), 'wave', mode: 'html_entity')->render();

    expect($html)->toContain('>&#x1F44B;</span>')
        ->not->toContain('&amp;');
});

function castRoundTrip(string $value): array
{
    $cast = new AsEmojiText;
    $model = new class extends Model {};
    $stored = $cast->set($model, 'bio', $value, []);

    return [$stored, $cast->get($model, 'bio', $stored, [])];
}

it('round-trips text through the cast, including shortcodes the user typed', function (string $value): void {
    [, $read] = castRoundTrip($value);

    expect($read)->toBe($value);
})->with([
    'typed shortcode'              => 'type :rocket: literally',
    'typed and real'               => 'ship :rocket: 🚀',
    'back to back'                 => ':rocket::rocket:🚀',
    'typed skin tone'              => ':wave::skin-tone-3:',
    'backslash before a shortcode' => 'C:\\ \\:rocket: and \\🚀',
    'plain backslashes'            => 'a\\b\\\\c',
    'emoji after typed code'       => ':rocket: 👋🏽',
]);

it('still stores emoji as shortcodes and reads existing rows back as emoji', function (): void {
    [$stored] = castRoundTrip('ship 🚀');
    $cast = new AsEmojiText;

    expect($stored)->toBe('ship :rocket:')
        ->and($cast->get(new class extends Model {}, 'bio', ':wave_tone3: :rocket:', []))->toBe('👋🏽 🚀');
});

it('fails the doctor when the configured image set does not resolve', function (): void {
    $result = new DatasetCheck(Emojis::create(['images' => ['set' => 'no-such-set']]))->run();

    expect($result->status)->toBe(DoctorStatus::Fail)
        ->and($result->message)->toContain('no-such-set');
});

it('passes the doctor with the default image set', function (): void {
    expect(new DatasetCheck(Emojis::create())->run()->status)->not->toBe(DoctorStatus::Fail);
});
