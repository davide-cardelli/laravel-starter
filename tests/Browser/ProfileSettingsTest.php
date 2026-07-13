<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('updates the profile information through the settings form', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'phone' => '+39 333 0000000',
    ]);

    actingAs($user);

    visit('/settings/profile')
        // type() replaces the pre-filled default values, so the submitted
        // payload matches exactly what we assert on the model below.
        ->type('#first_name', 'Grace')
        ->type('#last_name', 'Hopper')
        ->type('#phone', '+1 (555) 010-2030')
        ->click('[data-test="update-profile-button"]')
        // The "Saved." confirmation only renders after the server round-trip,
        // so seeing it guarantees the update completed before we assert.
        ->assertSee('Saved.');

    $user->refresh();

    expect($user->first_name)->toBe('Grace');
    expect($user->last_name)->toBe('Hopper');
    expect($user->phone)->toBe('+1 (555) 010-2030');
});

it('shows the validation error next to the field that failed', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'phone' => '+39 333 0000000',
    ]);

    actingAs($user);

    // An alphabetic phone passes the browser's native validation (required,
    // type="tel") but fails the server-side regex, so the round trip happens
    // and the InputError bound to the phone field must render the message.
    visit('/settings/profile')
        ->type('#phone', 'not-a-phone')
        ->click('[data-test="update-profile-button"]')
        ->assertSee('The phone field format is invalid.');

    expect($user->refresh()->phone)->toBe('+39 333 0000000');
});
