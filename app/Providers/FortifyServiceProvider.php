<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register'));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $emailInput = $request->input(Fortify::username(), '');
            $email = is_string($emailInput) ? $emailInput : '';
            $throttleKey = Str::transliterate(Str::lower($email).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Registration is throttled per source IP. A brand-new sign-up has no
        // prior identity to key on, and keying by the submitted email would let a
        // single host mass-create accounts under different addresses — so IP is
        // the right (if coarse) signal here; users behind a shared NAT share the
        // budget. `ip()` is effectively always non-null (the remote address).
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));

        // Password-reset requests are throttled per email+IP: a single source can
        // send at most 5/min for a given address. This caps abuse per source but
        // does not, by itself, make an address flood-proof against many IPs.
        RateLimiter::for('password-reset', function (Request $request) {
            $emailInput = $request->input('email', '');
            $email = is_string($emailInput) ? $emailInput : '';

            return Limit::perMinute(5)->by(Str::transliterate(Str::lower($email).'|'.$request->ip()));
        });

        // Fortify wires limiters for login/two-factor from config, but not for
        // register / forgot-password. Its routes are registered late (in a
        // booted callback), so attach the throttle from a nested booted
        // callback, which runs once those routes exist.
        $this->app->booted(function () {
            $this->app->booted(function () {
                $routes = Route::getRoutes();
                $routes->getByName('register.store')?->middleware('throttle:register');
                $routes->getByName('password.email')?->middleware('throttle:password-reset');
            });
        });
    }
}
