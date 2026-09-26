<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

/**
 * Pinned to php84, matching the `^8.4.1 || ^8.5` floor: the php85 set would rewrite code into syntax that
 * parses on the 8.5 job and fails on 8.4.
 *
 * Generated files are skipped: build-dataset.php and generate-enums.php --check assert them byte for byte,
 * so a Rector rewrite would put the file and its generator permanently at odds.
 */
return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/helpers'])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/resources/data',
        __DIR__ . '/src/Core/Enums/EmojiId.php',
        __DIR__ . '/src/Core/Enums/Group.php',
        __DIR__ . '/src/Core/Enums/Subgroup.php',
        __DIR__ . '/src/Core/Enums/EmojiVersion.php',
    ])
    ->withPhpSets(php84: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
    ])
    ->withImportNames(removeUnusedImports: true);
