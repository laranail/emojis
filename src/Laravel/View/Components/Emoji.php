<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Laravel\View\Components;

use BackedEnum;
use InvalidArgumentException;
use Illuminate\View\Component;
use Illuminate\Support\HtmlString;
use Simtabi\Laranail\Emojis\Core\Emojis;
use Simtabi\Laranail\Emojis\Core\Enums\Fit;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Support\Html;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Render\Renderer;

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

        $mode = $this->enum(Mode::class, 'mode', $this->mode);
        // One converter for every mode, so locale, set and fit apply wherever the output can show them.
        $converter = $this->emojis->text($emoji->char);
        $converter = $this->set !== null ? $converter->imageSet($this->set) : $converter;
        $converter = $this->locale !== null ? $converter->locale($this->locale) : $converter;
        $converter = $this->fit !== null ? $converter->fit($this->enum(Fit::class, 'fit', $this->fit)) : $converter;

        if ($mode === Mode::Image) {
            return new HtmlString($converter->to(Mode::Image));
        }

        $label = htmlspecialchars($emoji->name($this->locale), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
        $rendered = $converter->to($mode);
        // HtmlEntity output is already HTML ("&#x1F44B;"); escaping it again would print the reference.
        $body = $mode === Mode::HtmlEntity && Html::isCharacterReferences($rendered)
            ? $rendered
            : htmlspecialchars($rendered, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);

        return new HtmlString('<span class="' . Renderer::NATIVE_CLASSES . "\" role=\"img\" aria-label=\"{$label}\">{$body}</span>");
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    private function enum(string $enum, string $attribute, string $value): BackedEnum
    {
        return $enum::tryFrom($value) ?? throw new InvalidArgumentException(sprintf(
            'Unknown %s "%s"; expected one of: %s.',
            $attribute,
            $value,
            implode(', ', array_map(static fn (BackedEnum $case): string => (string) $case->value, $enum::cases())),
        ));
    }
}
