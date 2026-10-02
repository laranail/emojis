<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\Http\Resources;

use Illuminate\Http\Request;
use Simtabi\Laranail\Emojis\Core\Emoji;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One emoji over the API: Emoji::jsonSerialize(), then the name and keywords in the request's locale, every
 * shortcode and emoticon, the skin-tone variants and the status tag it is written as.
 *
 * @property Emoji $resource
 */
final class EmojiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $emoji = $this->resource;
        $locale = $request->query('locale');
        $locale = is_string($locale) && $locale !== '' ? $locale : null;

        return [
            ...$emoji->jsonSerialize(),
            'english_name' => $emoji->englishName,
            'name'         => $emoji->name($locale),
            'keywords'     => $emoji->keywords($locale),
            'shortcodes'   => $emoji->allShortcodes(),
            'emoticons'    => $emoji->emoticons(),
            'skins'        => array_map(strval(...), $emoji->skins),
            'tag'          => $emoji->tag()?->label,
        ];
    }
}
