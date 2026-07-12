<?php

declare(strict_types=1);

use App\Models\User;

it('renders the login page in a real browser', function () {
    visit('/login')
        ->assertSee('Email address')
        ->assertPresent('#email')
        ->assertPresent('#password')
        ->assertNoJavaScriptErrors();
});

it('logs a user in and lands on the dashboard', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'e2e.login@example.test',
    ]);

    visit('/login')
        ->type('#email', $user->email)
        ->type('#password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();
});

it('stops a two-factor user at the challenge instead of the dashboard', function () {
    // Factory users have two-factor enabled by default, so a correct password
    // must not complete the login: Fortify has to divert to the challenge.
    $user = User::factory()->create([
        'email' => 'e2e.twofactor@example.test',
    ]);

    visit('/login')
        ->type('#email', $user->email)
        ->type('#password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/two-factor-challenge')
        ->assertPresent('#otp');
});
