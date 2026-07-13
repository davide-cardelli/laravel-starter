<?php

declare(strict_types=1);

use App\Actions\User\UpdateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(TestCase::class, RefreshDatabase::class);

test('update user action updates user successfully', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'phone' => '+39 333 1111111',
        'email' => 'old@example.com',
    ]);

    $action = new UpdateUser;

    $updated = $action->execute($user, [
        'first_name' => 'New',
        'last_name' => 'Name',
        'phone' => '+39 333 2222222',
        'email' => 'new@example.com',
    ]);

    expect($updated->first_name)->toBe('New');
    expect($updated->last_name)->toBe('Name');
    expect($updated->phone)->toBe('+39 333 2222222');
    expect($updated->email)->toBe('new@example.com');

    assertDatabaseHas('users', [
        'id' => $user->id,
        'first_name' => 'New',
        'last_name' => 'Name',
        'email' => 'new@example.com',
    ]);
});

test('update user action updates password when provided', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();
    $oldPassword = $user->password;

    $action = new UpdateUser;

    $updated = $action->execute($user, [
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'phone' => $user->phone,
        'email' => $user->email,
        'password' => 'new-password',
    ]);

    expect($updated->password)->not->toBe($oldPassword);
    expect(Hash::check('new-password', $updated->password))->toBeTrue();
});

test('update user action does not update password when not provided', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();
    $oldPassword = $user->password;

    $action = new UpdateUser;

    $updated = $action->execute($user, [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'phone' => $user->phone,
        'email' => $user->email,
    ]);

    expect($updated->password)->toBe($oldPassword);
});

test('update user action logs operation', function () {
    Log::spy();

    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'phone' => '+39 333 1111111',
        'email' => 'old@example.com',
    ]);

    $action = new UpdateUser;

    $action->execute($user, [
        'first_name' => 'New',
        'last_name' => 'User',
        'phone' => '+39 333 2222222',
        'email' => 'new@example.com',
        'password' => 'new-password',
    ]);

    // Change FLAGS are logged, never the PII values themselves.
    Log::shouldHaveReceived('info')
        ->with('Updating user', Mockery::on(function ($context) use ($user, $admin) {
            return $context['user_id'] === $user->id &&
                   $context['updated_by'] === $admin->id &&
                   $context['changes']['first_name'] === true &&
                   $context['changes']['last_name'] === true &&
                   $context['changes']['phone'] === true &&
                   $context['changes']['email'] === true &&
                   $context['changes']['password'] === true &&
                   ! array_key_exists('email', $context);
        }))
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) use ($user) {
            return $message === 'User updated successfully' &&
                   $context['user_id'] === $user->id;
        })
        ->once();
});

test('update user action returns refreshed user instance', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create([
        'first_name' => 'Original',
        'last_name' => 'User',
        'phone' => '+39 333 1234567',
    ]);

    $action = new UpdateUser;

    $result = $action->execute($user, [
        'first_name' => 'Updated',
        'last_name' => 'User',
        'phone' => $user->phone,
        'email' => $user->email,
    ]);

    expect($result)->toBeInstanceOf(User::class);
    expect($result->first_name)->toBe('Updated');
    expect($result->wasRecentlyCreated)->toBeFalse();
});
