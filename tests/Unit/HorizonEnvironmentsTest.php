<?php

/*
 * Horizon starts workers only for environments listed in config/horizon.php.
 * A server whose APP_ENV is missing there runs Horizon with zero workers and
 * processes nothing, without any error.
 */
it('gives every deployable environment Horizon workers', function () {
    $environments = (require __DIR__.'/../../config/horizon.php')['environments'];

    foreach (['production', 'staging', 'local'] as $env) {
        expect($environments)->toHaveKey($env)
            ->and($environments[$env]['supervisor-1']['maxProcesses'] ?? 0)->toBeGreaterThan(0);
    }
});
