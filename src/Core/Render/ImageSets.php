<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Render;

use Simtabi\Laranail\Emojis\Core\Emoji;
use Simtabi\Laranail\Emojis\Core\Data\DatasetStore;
use Simtabi\Laranail\Emojis\Core\Contracts\ImageSet;
use Simtabi\Laranail\Emojis\Core\Enums\ImageSetName;
use Simtabi\Laranail\Emojis\Core\Render\ImageSets\Filenames;
use Simtabi\Laranail\Emojis\Core\Exceptions\ImageSetNotFound;
use Simtabi\Laranail\Emojis\Core\Render\ImageSets\CdnImageSet;
use Simtabi\Laranail\Emojis\Core\Exceptions\InvalidCustomEmoji;

/**
 * The registry of image sets: the built-ins, resolved by an exhaustive match on ImageSetName, plus any a
 * consumer registers by name. Not an Illuminate Manager — a Manager resolves a driver by interpolating its
 * name into a method call, which no type checks; here a name that is neither a case nor registered throws
 * ImageSetNotFound at the call.
 *
 * CDN versions are pinned to the release the coverage data was built from (never "@latest"), so a URL the
 * dataset says exists does exist. Point a set at a self-hosted mirror with a base URL override.
 */
final class ImageSets
{
    /** @var array<string, ImageSet> */
    private array $resolved = [];

    /** @var array<string, ImageSet> */
    private array $registered = [];

    /** @var array<string, CdnImageSet> the built-ins at their pinned CDN address, ignoring base URL overrides */
    private array $upstream = [];

    private bool $frozen = false;

    /** @param array<string, string> $baseUrls set name => base URL override */
    public function __construct(
        private readonly DatasetStore $data,
        private readonly array $baseUrls = [],
    ) {}

    public function register(ImageSet $set): self
    {
        if ($this->frozen) {
            throw InvalidCustomEmoji::frozen();
        }

        $this->registered[$set->name()] = $set;
        unset($this->resolved[$set->name()]);

        return $this;
    }

    public function freeze(): self
    {
        $this->frozen = true;

        return $this;
    }

    public function get(ImageSetName|string $name): ImageSet
    {
        $name = $name instanceof ImageSetName ? $name->value : $name;

        if (isset($this->registered[$name])) {
            return $this->registered[$name];
        }

        $builtIn = ImageSetName::tryFrom($name) ?? throw ImageSetNotFound::named($name, $this->names());

        return $this->resolved[$name] ??= $this->build($builtIn);
    }

    /**
     * The measured crop of an emoji in a built-in set: [inset, x, y, size] in permille, or null when the set
     * was not measured, the emoji not covered, or the set is a registered custom one.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    public function crop(Emoji $emoji, string $set): ?array
    {
        if (isset($this->registered[$set])) {
            return null;
        }

        $crop = $this->data->imageCrops($set)[$emoji->hexcode] ?? null;

        if ($crop === null) {
            return null;
        }

        $parts = array_map(intval(...), explode(' ', $crop));

        return count($parts) === 4 ? [$parts[0], $parts[1], $parts[2], $parts[3]] : null;
    }

    /**
     * A built-in set at its pinned CDN address even when a base URL override (self-hosting, `images.source`
     * local) is configured — where the installer downloads from.
     *
     * @throws ImageSetNotFound for a registered or unknown set
     */
    public function upstream(string $name): CdnImageSet
    {
        $builtIn = ImageSetName::tryFrom($name) ?? throw ImageSetNotFound::named($name, $this->names());

        return $this->upstream[$name] ??= $this->build($builtIn, ignoreOverrides: true);
    }

    public function has(string $name): bool
    {
        return isset($this->registered[$name]) || ImageSetName::tryFrom($name) !== null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_values(array_unique([...array_map(static fn (ImageSetName $n): string => $n->value, ImageSetName::cases()), ...array_keys($this->registered)]));
    }

    private function build(ImageSetName $name, bool $ignoreOverrides = false): CdnImageSet
    {
        $bits = $this->data->imageBits();
        $versions = $this->data->imageVersions();
        $fluent = $this->data->fluentFolders();
        $base = fn (string $default): string => $ignoreOverrides ? $default : ($this->baseUrls[$name->value] ?? $default);

        return match ($name) {
            ImageSetName::Twemoji => new CdnImageSet(
                $name->value,
                $base("https://cdn.jsdelivr.net/gh/jdecked/twemoji@{$versions['twemoji']}/assets/svg"),
                static fn (Emoji $e): string => Filenames::twemoji($e) . '.svg',
                $bits['twemoji'],
                'CC-BY-4.0 (graphics, attribution required) — jdecked/twemoji',
            ),
            ImageSetName::Noto => new CdnImageSet(
                $name->value,
                $base("https://cdn.jsdelivr.net/gh/googlefonts/noto-emoji@{$versions['noto']}/2D/svg"),
                static fn (Emoji $e): string => Filenames::noto($e) . '.svg',
                $bits['noto'],
                'Apache-2.0 — googlefonts/noto-emoji',
            ),
            ImageSetName::OpenMoji => new CdnImageSet(
                $name->value,
                $base("https://cdn.jsdelivr.net/npm/openmoji@{$versions['openmoji']}/color/svg"),
                static fn (Emoji $e): string => Filenames::openmoji($e) . '.svg',
                $bits['openmoji'],
                'CC-BY-SA-4.0 (attribution and share-alike required) — hfg-gmuend/openmoji',
            ),
            ImageSetName::Fluent => new CdnImageSet(
                $name->value,
                $base("https://cdn.jsdelivr.net/gh/microsoft/fluentui-emoji@{$versions['fluent']}/assets"),
                static function (Emoji $e) use ($fluent): ?string {
                    $folder = $fluent[$e->hexcode] ?? null;

                    if ($folder === null) {
                        return null;
                    }

                    // Fluent names files after the emoji's CLDR name, which can differ from its folder name:
                    // "O button blood type/Color/o_button_(blood_type)_color.svg".
                    $stem = strtolower(str_replace(' ', '_', $e->base()->englishName));
                    $path = rawurlencode($folder);

                    if ($e->tones !== []) {
                        $tone = ['light', 'medium-light', 'medium', 'medium-dark', 'dark'][$e->tones[0]->value - 1];

                        return "{$path}/" . ucwords($tone, '-') . "/Color/{$stem}_color_{$tone}.svg";
                    }

                    // Fluent ships tone folders only for single-person emoji; the handshake and couples are one image.
                    return $e->skinTonePeople() === 1 ? "{$path}/Default/Color/{$stem}_color_default.svg" : "{$path}/Color/{$stem}_color.svg";
                },
                $bits['fluent'],
                'MIT — microsoft/fluentui-emoji',
            ),
            ImageSetName::JoyPixels => new CdnImageSet(
                $name->value,
                $base('https://cdn.jsdelivr.net/joypixels/assets/11.0/png/unicode/64'),
                static fn (Emoji $e): string => strtolower(implode('-', array_map(static fn (int $cp): string => sprintf('%x', $cp), array_values(array_filter($e->codepoints, static fn (int $cp): bool => $cp !== 0xFE0F))))) . '.png',
                0,
                'JoyPixels Free License — personal use only; commercial use needs a paid licence',
            ),
        };
    }
}
