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

it('round-trips text through the cast', function (string $value): void {
    [$stored, $read] = castRoundTrip($value);

    expect($read)->toBe($value)
        ->and(preg_match('/[\xF0-\xF7]/', (string) $stored))->toBe(0);
})->with([
    'typed shortcode'              => 'type :rocket: literally',
    'typed and real'               => 'ship :rocket: 🚀',
    'back to back'                 => ':rocket::rocket:🚀',
    'typed skin tone'              => ':wave::skin-tone-3:',
    'backslash before a shortcode' => 'C:\\ \\:rocket: and \\🚀',
    'plain backslashes'            => 'a\\b\\\\c',
    'emoji after typed code'       => ':rocket: 👋🏽',
    'emoji touching letters'       => 'a🚀b and x👋🏽y',
    'times and ratios'             => '12:30:45 at 3:1',
    'emoji newer than the dataset' => "new \u{1FC00} face",
    'rare ideograph'               => '𠀀 is U+20000',
    'unicode escapes typed'        => 'type :U+1F680: literally',
    'empty'                        => '',
]);

it('stores a documented, escaped format', function (): void {
    expect(castRoundTrip('a🚀b')[0])->toBe('a:rocket:b')
        ->and(castRoundTrip('time 12:30 \\ :rocket:')[0])->toBe('time 12\\:30 \\\\ \\:rocket\\:')
        ->and(castRoundTrip("new \u{1FC00}")[0])->toBe('new :U+1FC00:');
});

it('reads rows written before 0.2, and now also converts a shortcode touching a letter', function (): void {
    $cast = new AsEmojiText;
    $model = new class extends Model {};

    expect($cast->get($model, 'bio', ':wave_tone3: :rocket:', []))->toBe('👋🏽 🚀')
        ->and($cast->get($model, 'bio', 'a:rocket:b', []))->toBe('a🚀b')
        ->and($cast->get($model, 'bio', 'C:\\Users 12:30', []))->toBe('C:\\Users 12:30')
        ->and($cast->get($model, 'bio', ':zz:rocket: :nope:', []))->toBe(':zz🚀 :nope:');
});

it('round-trips every emoji in the dataset through storage', function (): void {
    $emojis = app(Emojis::class);
    $inspected = 0;

    foreach ($emojis->all() as $emoji) {
        $stored = AsEmojiText::encode('x' . $emoji->char . 'y', $emojis);
        $inspected++;

        expect(AsEmojiText::decode($stored, $emojis))->toBe('x' . $emoji->char . 'y', $emoji->hexcode)
            ->and(preg_match('/[\xF0-\xF7]/', $stored))->toBe(0, $emoji->hexcode);
    }

    expect($inspected)->toBeGreaterThan(3900);
});

it('fails the doctor when the configured image set does not resolve', function (): void {
    $result = new DatasetCheck(Emojis::create(['images' => ['set' => 'no-such-set']]))->run();

    expect($result->status)->toBe(DoctorStatus::Fail)
        ->and($result->message)->toContain('no-such-set');
});

it('passes the doctor with the default image set', function (): void {
    expect(new DatasetCheck(Emojis::create())->run()->status)->not->toBe(DoctorStatus::Fail);
});
