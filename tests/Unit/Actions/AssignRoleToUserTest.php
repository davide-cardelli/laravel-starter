<?php

declare(strict_types=1);

use App\Actions\User\AssignRoleToUser;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('assign role action assigns role by name', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();

    $action = new AssignRoleToUser;
    $result = $action->execute($user, 'admin');

    expect($result->hasRole('admin'))->toBeTrue();
});

test('assign role action assigns role by model', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();
    $role = Role::findByName('admin');

    $action = new AssignRoleToUser;
    $result = $action->execute($user, $role);

    expect($result->hasRole('admin'))->toBeTrue();
});

test('assign role action logs operation with role name', function () {
    Log::spy();

    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create(['email' => 'test@example.com']);

    $action = new AssignRoleToUser;
    $action->execute($user, 'admin');

    // Identifiers only in the audit trail — the email is PII and stays out.
    Log::shouldHaveReceived('info')
        ->with('Assigning role to user', Mockery::on(function ($context) use ($user, $admin) {
            return $context['user_id'] === $user->id &&
                   $context['role'] === 'admin' &&
                   $context['assigned_by'] === $admin->id &&
                   ! array_key_exists('email', $context);
        }))
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) use ($user) {
            return $message === 'Role assigned successfully' &&
                   $context['user_id'] === $user->id &&
                   $context['role'] === 'admin';
        })
        ->once();
});

test('assign role action logs operation with role model', function () {
    Log::spy();

    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();
    $role = Role::findByName('admin');

    $action = new AssignRoleToUser;
    $action->execute($user, $role);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return $message === 'Assigning role to user' &&
                   $context['role'] === 'admin';
        })
        ->once();
});

test('assign role action returns refreshed user', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();

    $action = new AssignRoleToUser;
    $result = $action->execute($user, 'admin');

    expect($result)->toBeInstanceOf(User::class);
    expect($result->id)->toBe($user->id);
    expect($result->hasRole('admin'))->toBeTrue();
});

test('assign role action handles duplicate role assignment', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();
    $user->assignRole('admin');

    $action = new AssignRoleToUser;

    // Should not throw exception
    $result = $action->execute($user, 'admin');

    expect($result->hasRole('admin'))->toBeTrue();
    expect($result->roles->count())->toBe(1);
});
