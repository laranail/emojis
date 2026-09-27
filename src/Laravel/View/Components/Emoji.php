<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View\Components;

use InvalidArgumentException;
use Illuminate\View\Component;
use Illuminate\Support\HtmlString;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Fit;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;

/**
 * <x-laranail-emojis::emoji name="wave" />                              👋 (native)
 * <x-laranail-emojis::emoji name="wave" mode="image" set="noto" />      <img …>
 * <x-laranail-emojis::emoji name="wave" skin-tone="medium" />           👋🏽
 *
 * Native output is wrapped in <span role="img" aria-label="…"> so assistive technology reads the localized
 * name rather than the code points; image output carries the same label.
 */
final class Emoji extends Component
{
    public function __construct(
        private readonly Emojis $emojis,
        public string $name,
        public string $mode = 'emoji',
        public ?string $set = null,
        public ?string $skinTone = null,
        public ?string $locale = null,
        public ?string $fit = null,
    ) {}

    public function render(): HtmlString
    {
        $emoji = $this->emojis->get($this->name);

        if ($this->skinTone !== null && $this->skinTone !== '') {
            $tone = SkinTone::fromName($this->skinTone) ?? throw new InvalidArgumentException("Unknown skin tone \"{$this->skinTone}\".");
            $emoji = $emoji->withSkinTone($tone);
        }

        $mode = Mode::from($this->mode);

        if ($mode === Mode::Image) {
            $converter = $this->emojis->text($emoji->char);
            $converter = $this->set !== null ? $converter->imageSet($this->set) : $converter;
            $converter = $this->locale !== null ? $converter->locale($this->locale) : $converter;
            $converter = $this->fit !== null ? $converter->fit(Fit::from($this->fit)) : $converter;

            return new HtmlString($converter->to(Mode::Image));
        }

        $label = htmlspecialchars($emoji->name($this->locale), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        $body = htmlspecialchars($emoji->render($mode), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

        return new HtmlString("<span class=\"laranail-emoji\" role=\"img\" aria-label=\"{$label}\">{$body}</span>");
    }
}
