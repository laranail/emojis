<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Testing\AssertsNoFacadeCalls;

uses(AssertsNoFacadeCalls::class);

// The package's classes take Laravel's contracts through their constructors rather than reaching for
// facades or app()/config()/route(). A call that cannot be injected (rules and casts built with `new`,
// doctor checks package-tools builds with `new`, the static Blade directive) stays, marked on the line
// above with @facade-call-exempt and its reason. Measured 2026-11-25: 128 files, 15 marked calls.
it('keeps the package classes off facades and service-locating helpers', function (): void {
    $this->assertNoFacadeCallsIn(dirname(__DIR__, 2) . '/src', minFiles: 120);
});
