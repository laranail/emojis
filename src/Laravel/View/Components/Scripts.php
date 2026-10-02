<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View\Components;

use Illuminate\View\Component;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Exceptions\DatasetException;

/**
 * <x-laranail-emojis::scripts />                     inline the emoji picker module once, before </body>
 * <x-laranail-emojis::scripts :nonce="$cspNonce" />  under a strict Content-Security-Policy
 * <x-laranail-emojis::scripts link />                load the published file instead (cacheable; needs
 *                                                    `vendor:publish --tag=laranail::emojis-assets`)
 *
 * The module mounts every <x-laranail-emojis::picker /> on the page, including ones added later.
 */
final class Scripts extends Component
{
    public function __construct(
        public ?string $nonce = null,
        public bool $link = false,
    ) {}

    public function render(): HtmlString
    {
        $nonce = $this->nonce ?? (app()->bound(Vite::class) ? app(Vite::class)->cspNonce() : null);
        $attribute = $nonce === null || $nonce === '' ? '' : ' nonce="' . e($nonce) . '"';

        if ($this->link) {
            return new HtmlString('<script type="module" src="' . e(asset(Styles::PUBLISHED_PATH . '/js/picker.js')) . "\"{$attribute}></script>");
        }

        $path = Emojis::assetPath('js/picker.js');
        $source = is_file($path) ? file_get_contents($path) : false;

        if ($source === false || $source === '') {
            throw DatasetException::assetMissing('js/picker.js', $path);
        }

        // "</script" inside the module would end the element early; the escaped form means the same to JS.
        return new HtmlString("<script type=\"module\"{$attribute}>\n" . str_ireplace('</script', '<\/script', $source) . "\n</script>");
    }
}
