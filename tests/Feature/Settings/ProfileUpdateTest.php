<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+39 333 1234567',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->first_name)->toBe('Test');
    expect($user->last_name)->toBe('User');
    expect($user->phone)->toBe('+39 333 1234567');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+39 333 1234567',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('the sole super-admin cannot delete their own account', function () {
    $this->seed(RolePermissionSeeder::class);

    $superAdmin = User::factory()->superAdmin()->create();

    // Self-service deletion must honour the same last-super-admin invariant as
    // the admin panel, or the sole super-admin could lock the whole system out.
    $this
        ->actingAs($superAdmin)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ])
        ->assertStatus(403);

    // Blocked before logout: the account survives and the session is intact.
    expect($superAdmin->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($superAdmin);
});

test('a super-admin can delete their own account when another remains', function () {
    $this->seed(RolePermissionSeeder::class);

    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->superAdmin()->create(); // a second super-admin survives

    $this
        ->actingAs($superAdmin)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ])
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($superAdmin->fresh())->toBeNull();
});
