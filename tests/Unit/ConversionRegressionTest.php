<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Exceptions\UnsupportedConversion;

it('names the version cap when that is why a strict conversion failed', function (): void {
    $message = UnsupportedConversion::versionCapped('1FAE9', Mode::Emoji, EmojiVersion::V12_0)->getMessage();

    expect($message)->toContain('1FAE9')
        ->toContain('12.0')
        ->not->toContain('has no');
});
