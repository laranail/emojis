<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Emojis\Core\Data;

/**
 * The positional layout of a catalogue record in resources/data/emojis.php.
 *
 * Records are lists, not maps, to keep the shard small and fast to lint. The generator writes the field
 * order into the shard, and DatasetStore refuses a shard whose order differs from FIELDS — so a dataset from
 * another version fails loudly instead of silently reading every field one position off.
 */
final class Record
{
    public const int EMOJI = 0;

    public const int NAME = 1;

    public const int SLUG = 2;

    public const int ASCII = 3;

    public const int GROUP = 4;

    public const int SUBGROUP = 5;

    public const int VERSION = 6;

    public const int TYPE = 7;

    public const int COMPONENT = 8;

    public const int BASE = 9;

    public const int TONES = 10;

    public const int SKINS = 11;

    public const int TEXT = 12;

    public const int REGION = 13;

    public const int GENDER = 14;

    public const int HAIR = 15;

    public const int DIRECTION = 16;

    public const int IMAGES = 17;

    /** @var list<string> */
    public const array FIELDS = ['emoji', 'name', 'slug', 'ascii', 'group', 'subgroup', 'version', 'type', 'component', 'base', 'tones', 'skins', 'text', 'region', 'gender', 'hair', 'direction', 'img'];

    /** @return array<array-key, string> "1" => hexcode, "1-2" => hexcode, parsed from "1=HEX 1-2=HEX" */
    public static function skins(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $skins = [];

        foreach (explode(' ', $value) as $pair) {
            [$key, $hex] = explode('=', $pair, 2) + [1 => ''];
            $skins[$key] = $hex;
        }

        return $skins;
    }
}
