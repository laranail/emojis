<?php

declare(strict_types=1);

/**
 * Every generator, in the order they must run: the dataset first, then what is built from it.
 *
 * The one list that regenerate.php (write) and sync-check.php (--check) both walk. They used to keep their
 * own lists, and refresh.php ran only the first two, so the weekly refresh regenerated the dataset but not
 * the docs built from it, and its own sync-check gate would have failed on the first upstream rename.
 * tests/Unit/ToolingTest.php fails when a generator script exists that is not listed here.
 */
final class Generators
{
    /** @return array<string, string> label => script, relative to .dev/tools */
    public static function all(): array
    {
        return [
            'dataset'       => 'build-dataset.php',
            'enums'         => 'generate-enums.php',
            'emoticons doc' => 'emoticons-doc.php',
            'emoji list'    => 'emoji-list-doc.php',
            'tags doc'      => 'tags-doc.php',
        ];
    }
}
