<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View\Components;

use Illuminate\View\Component;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Simtabi\Laranail\Emojis\Core\Emojis;

/**
 * <x-laranail-emojis::styles />                     inline the emoji stylesheet once, in <head>
 * <x-laranail-emojis::styles :nonce="$cspNonce" />  under a strict Content-Security-Policy
 * <x-laranail-emojis::styles link />                link the published file instead (cacheable; needs
 *                                                   `vendor:publish --tag=laranail::emojis-assets`)
 * <x-laranail-emojis::styles picker />              also the emoji picker's stylesheet
 *
 * With Laravel's Vite nonce in use, the nonce is picked up automatically.
 */
final class Styles extends Component
{
    /** Where `laranail::emojis-assets` publishes public/assets, relative to the application's public/. */
    public const string PUBLISHED_PATH = 'vendor/laranail/emojis';

    public function __construct(
        private readonly Emojis $emojis,
        public ?string $nonce = null,
        public bool $link = false,
        public bool $picker = false,
    ) {}

    public function render(): HtmlString
    {
        $nonce = $this->nonce ?? (function_exists('app') && app()->bound(Vite::class) ? app(Vite::class)->cspNonce() : null);
        $attribute = $nonce === null || $nonce === '' ? '' : ' nonce="' . e($nonce) . '"';

        $names = $this->picker ? ['emojis', 'picker'] : ['emojis'];

        if ($this->link) {
            return new HtmlString(implode("\n", array_map(
                static fn (string $name): string => '<link rel="stylesheet" href="' . e(asset(self::PUBLISHED_PATH . "/css/{$name}.css")) . "\"{$attribute}>",
                $names,
            )));
        }

        return new HtmlString("<style{$attribute}>\n" . implode("\n", array_map($this->emojis->stylesheet(...), $names)) . "\n</style>");
    }
}
