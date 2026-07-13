<?php

declare(strict_types=1);

use App\Actions\User\RemoveRoleFromUser;
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

test('remove role action removes role by name', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->hasRole('admin'))->toBeTrue();

    $action = new RemoveRoleFromUser;
    $result = $action->execute($user, 'admin');

    expect($result->hasRole('admin'))->toBeFalse();
});

test('remove role action removes role by model', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();
    $role = Role::findByName('admin');
    $user->assignRole($role);

    $action = new RemoveRoleFromUser;
    $result = $action->execute($user, $role);

    expect($result->hasRole('admin'))->toBeFalse();
});

test('remove role action logs operation with role name', function () {
    Log::spy();

    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create(['email' => 'test@example.com']);
    $user->assignRole('admin');

    $action = new RemoveRoleFromUser;
    $action->execute($user, 'admin');

    // Identifiers only in the audit trail — the email is PII and stays out.
    Log::shouldHaveReceived('info')
        ->with('Removing role from user', Mockery::on(function ($context) use ($user, $admin) {
            return $context['user_id'] === $user->id &&
                   $context['role'] === 'admin' &&
                   $context['removed_by'] === $admin->id &&
                   ! array_key_exists('email', $context);
        }))
        ->once();

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) use ($user) {
            return $message === 'Role removed successfully' &&
                   $context['user_id'] === $user->id &&
                   $context['role'] === 'admin';
        })
        ->once();
});

test('remove role action returns refreshed user', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();
    $user->assignRole('admin');

    $action = new RemoveRoleFromUser;
    $result = $action->execute($user, 'admin');

    expect($result)->toBeInstanceOf(User::class);
    expect($result->id)->toBe($user->id);
    expect($result->hasRole('admin'))->toBeFalse();
});

test('remove role action handles non-existing role removal', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();
    // User doesn't have 'admin' role

    $action = new RemoveRoleFromUser;

    // Should not throw exception
    $result = $action->execute($user, 'admin');

    expect($result->hasRole('admin'))->toBeFalse();
    expect($result->roles->count())->toBe(0);
});

test('remove role action preserves other roles', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create();
    $user->assignRole(['admin', 'user']);

    $action = new RemoveRoleFromUser;
    $result = $action->execute($user, 'admin');

    expect($result->hasRole('admin'))->toBeFalse();
    expect($result->hasRole('user'))->toBeTrue();
    expect($result->roles->count())->toBe(1);
});
