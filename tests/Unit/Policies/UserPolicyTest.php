<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\User;
use App\Policies\UserPolicy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Persona holding every user-management permission EXCEPT assign roles. The
    // negative assign/remove tests use it so they fail loudly if the policy ever
    // checks the wrong user-management permission (e.g. edit users) instead of
    // assign roles.
    Role::create(['name' => 'user-manager'])->givePermissionTo([
        Permission::ViewUsers->value,
        Permission::CreateUsers->value,
        Permission::EditUsers->value,
        Permission::DeleteUsers->value,
    ]);
});

// VIEW ANY TESTS
test('user with view users permission can view any user', function () {
    $user = User::factory()->withRole('admin')->create();

    expect((new UserPolicy)->viewAny($user))->toBeTrue();
});

test('user without view users permission cannot view any user', function () {
    $user = User::factory()->withRole('user')->create();

    expect((new UserPolicy)->viewAny($user))->toBeFalse();
});

// VIEW TESTS
test('user with view users permission can view specific user', function () {
    $user = User::factory()->withRole('admin')->create();
    $targetUser = User::factory()->create();

    expect((new UserPolicy)->view($user, $targetUser))->toBeTrue();
});

test('user without view users permission cannot view specific user', function () {
    $user = User::factory()->withRole('user')->create();
    $targetUser = User::factory()->create();

    expect((new UserPolicy)->view($user, $targetUser))->toBeFalse();
});

// CREATE TESTS
test('user with create users permission can create user', function () {
    $user = User::factory()->withRole('admin')->create();

    expect((new UserPolicy)->create($user))->toBeTrue();
});

test('user without create users permission cannot create user', function () {
    $user = User::factory()->withRole('user')->create();

    expect((new UserPolicy)->create($user))->toBeFalse();
});

// UPDATE TESTS
test('user with edit users permission can update other users', function () {
    $user = User::factory()->withRole('admin')->create();
    $targetUser = User::factory()->create();

    expect((new UserPolicy)->update($user, $targetUser))->toBeTrue();
});

test('user without edit users permission cannot update other users', function () {
    $user = User::factory()->withRole('user')->create();
    $targetUser = User::factory()->create();

    expect((new UserPolicy)->update($user, $targetUser))->toBeFalse();
});

// DELETE TESTS
test('user with delete users permission can delete other users', function () {
    $user = User::factory()->superAdmin()->create();
    $targetUser = User::factory()->create();

    expect((new UserPolicy)->delete($user, $targetUser))->toBeTrue();
});

test('user cannot delete themselves', function () {
    $user = User::factory()->superAdmin()->create();

    expect((new UserPolicy)->delete($user, $user))->toBeFalse();
});

test('user without delete users permission cannot delete users', function () {
    $user = User::factory()->withRole('user')->create();
    $targetUser = User::factory()->create();

    expect((new UserPolicy)->delete($user, $targetUser))->toBeFalse();
});

// RANK GUARD TESTS (a lower-ranked actor must not edit/delete one who outranks them)
test('admin cannot update a super-admin', function () {
    $admin = User::factory()->withRole('admin')->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect((new UserPolicy)->update($admin, $superAdmin))->toBeFalse();
});

test('admin cannot delete a super-admin', function () {
    $admin = User::factory()->withRole('admin')->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect((new UserPolicy)->delete($admin, $superAdmin))->toBeFalse();
});

test('super-admin can update and delete an admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->withRole('admin')->create();

    expect((new UserPolicy)->update($superAdmin, $admin))->toBeTrue();
    expect((new UserPolicy)->delete($superAdmin, $admin))->toBeTrue();
});

test('an admin can manage another admin of equal rank', function () {
    $admin = User::factory()->withRole('admin')->create();
    $peer = User::factory()->withRole('admin')->create();

    expect((new UserPolicy)->update($admin, $peer))->toBeTrue();
    expect((new UserPolicy)->delete($admin, $peer))->toBeTrue();
});

// ASSIGN ROLE TESTS
test('user with assign roles permission can assign roles', function () {
    $user = User::factory()->superAdmin()->create();

    expect((new UserPolicy)->assignRole($user))->toBeTrue();
});

test('user without assign roles permission cannot assign roles', function () {
    // Holds every user-management permission except assign roles.
    $user = User::factory()->withRole('user-manager')->create();

    expect((new UserPolicy)->assignRole($user))->toBeFalse();
});

// REMOVE ROLE TESTS
test('user with assign roles permission can remove roles', function () {
    $user = User::factory()->superAdmin()->create();

    expect((new UserPolicy)->removeRole($user))->toBeTrue();
});

test('user without assign roles permission cannot remove roles', function () {
    $user = User::factory()->withRole('user-manager')->create();

    expect((new UserPolicy)->removeRole($user))->toBeFalse();
});
