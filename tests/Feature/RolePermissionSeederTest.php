<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('super-admin holds every permission on its guard, including ones added outside the enum', function () {
    // A permission registered outside App\Enums\Permission — e.g. by a package,
    // a migration, or a downstream seeder — on the default (web) guard.
    Permission::create(['name' => 'operate the reactor']);

    $this->seed(RolePermissionSeeder::class);

    expect(Role::findByName('super-admin')->hasPermissionTo('operate the reactor'))->toBeTrue();
});

test('seeding tolerates permissions registered on another guard', function () {
    // Guards against Spatie's GuardDoesNotMatch: super-admin syncs only its own
    // guard's permissions, so an unrelated guard's permission must not abort seeding.
    Permission::create(['name' => 'call api', 'guard_name' => 'api']);

    $this->seed(RolePermissionSeeder::class);

    $superAdmin = Role::findByName('super-admin');

    expect($superAdmin->hasPermissionTo('view users'))->toBeTrue()
        ->and($superAdmin->permissions->pluck('name')->all())->not->toContain('call api');
});
