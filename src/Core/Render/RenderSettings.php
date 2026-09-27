<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Render;

use Simtabi\Laranail\Emojis\Core\Enums\Fit;
use Simtabi\Laranail\Emojis\Core\Enums\Mode;
use Simtabi\Laranail\Emojis\Core\Enums\Carrier;
use Simtabi\Laranail\Emojis\Core\Enums\SkinTone;
use Simtabi\Laranail\Emojis\Core\Enums\EmojiVersion;
use Simtabi\Laranail\Emojis\Core\Enums\EscapeFormat;
use Simtabi\Laranail\Emojis\Core\Enums\ShortcodePreset;

/** The per-conversion choices a TextConverter hands the Renderer. */
final readonly class RenderSettings
{
    /** @param array<string, list<Mode>> $chains target mode value => degradation chain override */
    public function __construct(
        public ShortcodePreset $preset,
        public ?string $locale = null,
        public ?string $imageSet = null,
        public ?EmojiVersion $versionCap = null,
        public bool $strict = false,
        public EscapeFormat $escapeFormat = EscapeFormat::Php,
        public ?SkinTone $skinTone = null,
        public array $chains = [],
        public Carrier $carrier = Carrier::Google,
        public ?Fit $fit = null,
    ) {}

    /**
     * @param list<Mode> $default
     *
     * @return list<Mode>
     */
    public function chainFor(Mode $target, array $default): array
    {
        return $this->chains[$target->value] ?? $default;
    }

    public function withoutSkinTone(): self
    {
        return new self($this->preset, $this->locale, $this->imageSet, $this->versionCap, $this->strict, $this->escapeFormat, null, $this->chains, $this->carrier, $this->fit);
    }
}
