<?php

declare(strict_types=1);

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('a missing page renders the Inertia error page', function () {
    $this->get('/a-page-that-does-not-exist')
        ->assertStatus(404)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/Error')
            ->where('status', 404));
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
