<?php

use Illuminate\Support\Facades\DB;

/*
| CI sets EXPECTED_DB_DRIVER=mysql for the full suite. This guards against
| tests silently falling back to SQLite and hiding MySQL-specific issues.
*/
it('runs the suite on the expected database driver', function () {
    $expected = getenv('EXPECTED_DB_DRIVER') ?: null;

    if ($expected === null) {
        $this->markTestSkipped('No expected driver set (local run).');
    }

    expect(DB::connection()->getDriverName())->toBe($expected);
});
