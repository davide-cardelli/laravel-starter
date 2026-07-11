<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Surface N+1 queries, silently discarded fills and accesses to
        // missing attributes as exceptions while developing and testing.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Refuse migrate:fresh, db:wipe and similar commands in production.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Immutable dates cannot be mutated in place by accident when the
        // same instance is shared across the codebase.
        Date::use(CarbonImmutable::class);

        // Behind a TLS-terminating proxy the framework would otherwise
        // generate http:// links in production.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Single source of truth for the password policy used by Fortify
        // and the user management forms; relaxed locally so demo
        // credentials and factories stay convenient.
        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(12)->uncompromised()
            : Password::min(8));
    }
}
