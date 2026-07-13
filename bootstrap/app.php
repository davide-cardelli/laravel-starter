<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind a TLS-terminating proxy (load balancer, Cloudflare, nginx)
        // the request IP would otherwise be the proxy's own, collapsing the
        // per-IP rate limiters (login, register, password reset) into a
        // single shared bucket and breaking secure-cookie/https detection.
        // '*' trusts the immediate upstream hop; if clients can also reach
        // the app directly, replace it with your proxy's explicit CIDRs
        // (env() is unavailable here once config is cached, so hardcode or
        // read from config).
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            // Invalidates sessions whose stored password hash no longer
            // matches the user's — this is what makes logoutOtherDevices()
            // (password change) actually kick the other sessions out.
            AuthenticateSession::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            // Keep framework defaults locally (Ignition) and for API/XHR callers
            // (e.g. Inertia's useHttp, which expects JSON): only real page
            // requests get the custom Inertia error pages.
            if (app()->environment('local') || $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if (in_array($status, [403, 404, 500, 503], true)) {
                try {
                    return Inertia::render('errors/Error', ['status' => $status])
                        ->toResponse($request)
                        ->setStatusCode($status);
                } catch (Throwable $e) {
                    // Rendering the Inertia page runs HandleInertiaRequests::share(),
                    // which hits the database. During an infrastructure outage that
                    // throws again, so fall back to a static view — never mask the
                    // error with an unhandled exception. Still report it, so a
                    // non-outage render regression leaves a log trail.
                    report($e);

                    return response()->view('errors.fallback', ['status' => $status], $status);
                }
            }

            // A 419 means the CSRF token / session expired: surface it through
            // the existing toast (flash) system rather than a full error page.
            if ($status === 419) {
                return back()->with('error', 'Your session expired. Please refresh and try again.');
            }

            return $response;
        });
    })->create();
