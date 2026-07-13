<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password update page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('user-password.edit'));

    $response->assertStatus(200);
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user-password.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('the current session survives a password change', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    // logoutOtherDevices() refreshes this session's password hash, so the
    // device that performed the change stays logged in.
    $this->get(route('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

test('other sessions are logged out after a password change', function () {
    $user = User::factory()->create();

    // Simulate another device: a session whose stored password hash still
    // matches the OLD password after the password has changed elsewhere.
    $this->actingAs($user);
    session()->put('password_hash_web', $user->password);

    $user->update(['password' => 'new-password']);

    // AuthenticateSession (web group) spots the stale hash and logs out.
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('user-password.edit'));
});
