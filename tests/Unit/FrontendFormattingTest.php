<?php

/*
 * Pages render on the server (Node) and again in the browser. Locale-based
 * number formatting differs between the two (en-ZA uses a decimal comma in
 * browsers), which breaks hydration and shows wrong amounts. Use the shared
 * formatters in resources/js/lib (rand, randCents, formatDateTime) instead.
 */
it('formats money without locale-dependent APIs', function () {
    $offenders = [];
    $root = dirname(__DIR__, 2);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js'));

    foreach ($files as $file) {
        if (! in_array($file->getExtension(), ['ts', 'tsx'], true) || str_contains($file->getPathname(), '/routes/') || str_contains($file->getPathname(), '/actions/')) {
            continue;
        }
        if (preg_match('/\.toLocaleString\(|new Intl\.NumberFormat/', (string) file_get_contents($file->getPathname()))) {
            $offenders[] = str_replace($root.'/', '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});
