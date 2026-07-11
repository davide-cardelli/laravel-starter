<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

afterEach(function () {
    // Booting the provider under a production environment mutates global
    // framework state; restore the testing defaults so later tests are clean.
    Model::shouldBeStrict();
    DB::prohibitDestructiveCommands(false);
    Password::defaults(fn () => Password::min(8));
});

test('secure session cookies are enforced in production', function () {
    config(['session.secure' => false]);
    $this->app['env'] = 'production';

    (new AppServiceProvider($this->app))->boot();

    expect(config('session.secure'))->toBeTrue();
});

test('session cookie security is left to configuration outside production', function () {
    config(['session.secure' => false]);

    // Default test environment is "testing": local http development must keep
    // working, so the provider must not force https-only cookies here.
    (new AppServiceProvider($this->app))->boot();

    expect(config('session.secure'))->toBeFalse();
});
