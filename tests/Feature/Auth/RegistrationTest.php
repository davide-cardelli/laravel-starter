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
