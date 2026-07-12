<?php

declare(strict_types=1);

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

test('a missing page renders the Inertia error page', function () {
    $this->get('/a-page-that-does-not-exist')
        ->assertStatus(404)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/Error')
            ->where('status', 404));
});

test('the error page renders for real Inertia navigations too', function () {
    // Real Inertia visits send Accept: text/html (so expectsJson() is false) plus
    // the X-Inertia header, which yields a JSON Inertia response carrying the
    // errors/Error component — so the custom page is not bypassed for SPA visits.
    $response = $this->get('/a-page-that-does-not-exist', [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ]);

    $response->assertStatus(404);
    expect($response->headers->get('X-Inertia'))->toBe('true');
    $response->assertJsonPath('component', 'errors/Error')
        ->assertJsonPath('props.status', 404);
});

test('the error page falls back to a static view when the Inertia render itself fails', function () {
    // Simulate an infrastructure failure during error rendering: an eagerly-resolved
    // shared prop throws while building the branded page (as share()'s DB-backed
    // auth/permission props would during an outage). The handler must still return
    // a 500 (the static fallback), never an unhandled exception.
    Inertia::share('crashDuringRender', fn () => throw new RuntimeException('db down'));
    Route::middleware('web')->get('/__boom-render', fn () => throw new RuntimeException('boom'));

    $this->get('/__boom-render')
        ->assertStatus(500)
        ->assertSee('Something went wrong');
});

test('a server error renders the Inertia error page', function () {
    Route::middleware('web')->get('/__boom', fn () => throw new RuntimeException('boom'));

    $this->get('/__boom')
        ->assertStatus(500)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/Error')
            ->where('status', 500));
});

test('an expired session (419) redirects back with a flash error for the toast', function () {
    Route::middleware('web')->get('/__expired', fn () => throw new TokenMismatchException);

    $this->get('/__expired')
        ->assertRedirect()
        ->assertSessionHas('error');
});
