<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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

        if ($this->app->isProduction()) {
            // Behind a TLS-terminating proxy the framework would otherwise
            // generate http:// links in production.
            URL::forceScheme('https');
        }

        // Enforce Secure session cookies on every TLS-served environment
        // (production, staging, preview) — only local/testing run over plain
        // http. This holds regardless of what .env.example ships.
        if (! $this->app->environment('local', 'testing')) {
            config(['session.secure' => true]);
        }

        // Single source of truth for the password policy used by Fortify
        // and the user management forms; relaxed locally so demo
        // credentials and factories stay convenient.
        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(12)->uncompromised()
            : Password::min(8));
    }
}
