<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

test('two factor challenge redirects to login when not authenticated', function () {
    $this->get(route('two-factor.login'))
        ->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/TwoFactorChallenge'));
});

test('a valid TOTP code completes the login', function () {
    $user = User::factory()->withTwoFactor()->create();
    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $this->assertGuest();

    $this->post(route('two-factor.login'), ['code' => $code]);

    $this->assertAuthenticatedAs($user);
});

test('an invalid TOTP code is rejected', function () {
    $user = User::factory()->withTwoFactor()->create();
    $valid = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    $invalid = $valid === '000000' ? '111111' : '000000';

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post(route('two-factor.login'), ['code' => $invalid])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a recovery code authenticates once and is then consumed', function () {
    $user = User::factory()->withTwoFactor()->create();

    /** @var array<int, string> $codes */
    $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);
    $recoveryCode = $codes[0];

    // First use: the recovery code authenticates.
    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $this->post(route('two-factor.login'), ['recovery_code' => $recoveryCode]);
    $this->assertAuthenticatedAs($user);

    // Log out and try the same recovery code again: it has been consumed.
    $this->post(route('logout'));
    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $this->post(route('two-factor.login'), ['recovery_code' => $recoveryCode])
        ->assertSessionHasErrors('recovery_code');
    $this->assertGuest();
});

test('the two factor challenge is rate limited', function () {
    $user = User::factory()->withTwoFactor()->create();
    $valid = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));
    $invalid = $valid === '000000' ? '111111' : '000000';

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // The 'two-factor' limiter allows 5 attempts per minute; the 6th is blocked.
    foreach (range(1, 5) as $ignored) {
        $this->post(route('two-factor.login'), ['code' => $invalid]);
    }

    $this->post(route('two-factor.login'), ['code' => $invalid])
        ->assertTooManyRequests();
});
