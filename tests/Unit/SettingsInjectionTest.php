<?php

/*
 * Settings objects are request-scoped. A class that receives one in its
 * constructor can be kept alive (controllers are cached on routes; Octane
 * workers live across requests) and keep reading stale values. Read settings
 * where they are used instead: app(SomeSettings::class)->value.
 */
it('never injects settings into constructors', function () {
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = (string) file_get_contents($file->getPathname());
        if (preg_match('/function __construct\([^)]*\b\w+Settings \$/s', $code)) {
            $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});
