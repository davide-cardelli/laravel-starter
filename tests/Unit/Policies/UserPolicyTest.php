<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'view users']);
    Permission::create(['name' => 'create users']);
    Permission::create(['name' => 'edit users']);
    Permission::create(['name' => 'delete users']);
    Permission::create(['name' => 'assign roles']);

    // Create roles
    $superAdmin = Role::create(['name' => 'super-admin']);
    $superAdmin->givePermissionTo(['view users', 'create users', 'edit users', 'delete users', 'assign roles']);

    $admin = Role::create(['name' => 'admin']);
    $admin->givePermissionTo(['view users', 'create users', 'edit users']);

    Role::create(['name' => 'user']);
});

// VIEW ANY TESTS
test('user with view users permission can view any user', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $policy = new UserPolicy;

    expect($policy->viewAny($user))->toBeTrue();
});

test('user without view users permission cannot view any user', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $policy = new UserPolicy;

    expect($policy->viewAny($user))->toBeFalse();
});

// VIEW TESTS
test('user with view users permission can view specific user', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $targetUser = User::factory()->create();

    $policy = new UserPolicy;

    expect($policy->view($user, $targetUser))->toBeTrue();
});

test('user without view users permission cannot view specific user', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $targetUser = User::factory()->create();

    $policy = new UserPolicy;

    expect($policy->view($user, $targetUser))->toBeFalse();
});

// CREATE TESTS
test('user with create users permission can create user', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $policy = new UserPolicy;

    expect($policy->create($user))->toBeTrue();
});

test('user without create users permission cannot create user', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $policy = new UserPolicy;

    expect($policy->create($user))->toBeFalse();
});

// UPDATE TESTS
test('user with edit users permission can update other users', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $targetUser = User::factory()->create();

    $policy = new UserPolicy;

    expect($policy->update($user, $targetUser))->toBeTrue();
});

test('user without edit users permission cannot update other users', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $targetUser = User::factory()->create();

    $policy = new UserPolicy;

    expect($policy->update($user, $targetUser))->toBeFalse();
});

// DELETE TESTS
test('user with delete users permission can delete other users', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $targetUser = User::factory()->create();

    $policy = new UserPolicy;

    expect($policy->delete($user, $targetUser))->toBeTrue();
});

test('user cannot delete themselves', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $policy = new UserPolicy;

    expect($policy->delete($user, $user))->toBeFalse();
});

test('user without delete users permission cannot delete users', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $targetUser = User::factory()->create();

    $policy = new UserPolicy;

    expect($policy->delete($user, $targetUser))->toBeFalse();
});

// ASSIGN ROLE TESTS
test('user with assign roles permission can assign roles', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $policy = new UserPolicy;

    expect($policy->assignRole($user))->toBeTrue();
});

test('user without assign roles permission cannot assign roles', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $policy = new UserPolicy;

    expect($policy->assignRole($user))->toBeFalse();
});

// REMOVE ROLE TESTS
test('user with assign roles permission can remove roles', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $policy = new UserPolicy;

    expect($policy->removeRole($user))->toBeTrue();
});

test('user without assign roles permission cannot remove roles', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $policy = new UserPolicy;

    expect($policy->removeRole($user))->toBeFalse();
});
