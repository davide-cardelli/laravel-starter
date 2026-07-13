<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '+39 333 1234567',
        'email' => 'test@example.com',
        'password' => 'Xk7$mP!9qL2b',
        'password_confirmation' => 'Xk7$mP!9qL2b',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    // The account starts unverified: the verification email must go out and
    // the dashboard must bounce the user to the verification screen until
    // they confirm the address.
    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);

    $this->get(route('dashboard'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('registration rejects a password below the default policy', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '+39 333 1234567',
        'email' => 'weak@example.com',
        'password' => 'short12',
        'password_confirmation' => 'short12',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('registration canonicalizes uppercase emails to lowercase', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '+39 333 1234567',
        'email' => 'Case.Variant@Example.com',
        'password' => 'Xk7$mP!9qL2b',
        'password_confirmation' => 'Xk7$mP!9qL2b',
    ]);

    // Fortify's lowercase_usernames canonicalizes the address before our
    // validation runs, so mixed case is normalized rather than rejected here
    // (the admin forms, which skip Fortify, reject it instead).
    $response->assertSessionHasNoErrors();
    expect(User::where('email', 'case.variant@example.com')->exists())->toBeTrue();
});

test('registration rejects a phone number without digits', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '+ () -',
        'email' => 'nodigits@example.com',
        'password' => 'Xk7$mP!9qL2b',
        'password_confirmation' => 'Xk7$mP!9qL2b',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
});
