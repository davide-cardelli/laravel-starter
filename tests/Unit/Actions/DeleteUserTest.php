<?php

declare(strict_types=1);

use App\Actions\User\DeleteUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseMissing;

uses(TestCase::class, RefreshDatabase::class);

test('delete user action deletes user successfully', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();

    $action = new DeleteUser;

    $result = $action->execute($user);

    expect($result)->toBeTrue();

    assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});

test('delete user action logs at warning level', function () {
    Log::spy();

    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'phone' => '+39 333 1234567',
        'email' => 'test@example.com',
    ]);

    $action = new DeleteUser;
    $action->execute($user);

    Log::shouldHaveReceived('warning')
        ->with('Deleting user', Mockery::on(function ($context) use ($user, $admin) {
            return $context['user_id'] === $user->id &&
                   $context['email'] === 'test@example.com' &&
                   $context['name'] === 'Test User' &&
                   $context['deleted_by'] === $admin->id;
        }))
        ->once();
});

test('delete user action logs success', function () {
    Log::spy();

    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create(['email' => 'delete@example.com']);

    $action = new DeleteUser;
    $action->execute($user);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return $message === 'User deleted successfully' &&
                   $context['email'] === 'delete@example.com';
        })
        ->once();
});

test('delete user action returns false on failure', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();

    // Use partial mock to override only the delete method
    $partialMock = Mockery::mock($user)->makePartial();
    $partialMock->shouldReceive('delete')->andReturn(false);

    Log::spy();

    $action = new DeleteUser;
    $result = $action->execute($partialMock);

    expect($result)->toBeFalse();

    Log::shouldHaveReceived('error')
        ->with('Failed to delete user', Mockery::type('array'))
        ->once();
});
