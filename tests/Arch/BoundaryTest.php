<?php

declare(strict_types=1);

use Simtabi\Laranail\Emojis\Core\Exceptions\EmojisException;

/*
 * src/Core is framework-free. deptrac enforces it statically (tools/deptrac-guard.php), tools/core-isolation.php
 * proves it with an autoloader that refuses everything outside Core, and this is the third, cheapest layer.
 */
arch('Core uses no framework and no other laranail package')
    ->expect('Simtabi\Laranail\Emojis\Core')
    ->not->toUse(['Illuminate', 'Symfony', 'Laravel', 'Simtabi\Laranail\Package', 'Simtabi\Laranail\Console']);

arch('every source file declares strict types')
    ->expect('Simtabi\Laranail\Emojis')
    ->toUseStrictTypes();

arch('exceptions implement the package marker')
    ->expect('Simtabi\Laranail\Emojis\Core\Exceptions')
    ->classes()
    ->toImplement(EmojisException::class);

/*
 * The scanner's portability rests on never using a PCRE Unicode emoji property: PHP builds linking PCRE2
 * older than 10.40 (MAMP ships 10.36) reject them at compile time, and newer ones lag Unicode. The dataset's
 * generated character classes replace them. This reads the source, because the defect would live in a
 * pattern string that a test on a modern PCRE would happily run.
 */
it('uses no PCRE emoji property anywhere in src', function (): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src', FilesystemIterator::SKIP_DOTS));
    $checked = 0;
    $offenders = [];

    foreach ($files as $file) {
        if (! str_ends_with((string) $file, '.php')) {
            continue;
        }

        $checked++;
        $source = (string) file_get_contents((string) $file);

        if (preg_match('/\\\\p\{(?:Extended_Pictographic|ExtPict|Emoji\w*|EPres|EMod\w*|EBase|EComp|RGI\w*|RI|Regional_Indicator)\}/', $source) === 1) {
            $offenders[] = basename((string) $file);
        }
    }

    expect($checked)->toBeGreaterThan(40)
        ->and($offenders)->toBe([]);
});
