<?php

arch('no debugging calls are left in the code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('domain controllers are suffixed and extend the base controller')
    ->expect('App\Domains\*\Http\Controllers')
    ->toHaveSuffix('Controller')
    ->toExtend('App\Http\Controllers\Controller');

arch('middleware lives in the HTTP layer')
    ->expect('App\Domains\*\Http\Middleware')
    ->toHaveMethod('handle');

arch('strict equality is used')
    ->expect('App')
    ->not->toUse(['eval']);
