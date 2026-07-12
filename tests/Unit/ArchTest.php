<?php

declare(strict_types=1);

// Architecture tests encode the template's rules as executable gates. They
// complement Deptrac (which enforces layer dependencies) by pinning conventions
// that are easy to violate silently.

arch('the whole app declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug helpers leak into the app')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump', 'var_export', 'die']);

arch('actions stay free of the http layer')
    ->expect('App\Actions')
    ->not->toUse(['App\Http\Controllers', 'App\Http\Requests']);

arch('models remain pure domain')
    ->expect('App\Models')
    ->not->toUse(['App\Http', 'App\Actions', 'App\Policies']);

arch('policies only serve authorization')
    ->expect('App\Policies')
    ->not->toUse(['App\Http', 'App\Actions']);

arch('enums are leaf domain primitives')
    ->expect('App\Enums')
    ->not->toUse(['App\Http', 'App\Actions', 'App\Models', 'App\Policies']);

arch('enums are backed by strings')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();
