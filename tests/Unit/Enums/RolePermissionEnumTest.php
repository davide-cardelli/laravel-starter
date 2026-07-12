<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;

test('permission enum exposes exactly the seeded permission names', function () {
    expect(Permission::values())->toBe([
        'view users',
        'create users',
        'edit users',
        'delete users',
        'assign roles',
        'view settings',
        'edit settings',
        'view content',
        'create content',
        'edit content',
        'delete content',
        'publish content',
    ]);
});

test('role enum exposes exactly the seeded role names', function () {
    expect(Role::values())->toBe(['super-admin', 'admin', 'manager', 'user']);
});

test('each role grants the expected permission set', function () {
    // Guards the enum against drifting from what RolePermissionSeeder seeds.
    expect(Role::SuperAdmin->permissions())->toHaveCount(12)
        ->and(Role::Admin->permissions())->toHaveCount(12)
        ->and(Role::Manager->permissions())->toHaveCount(5)
        ->and(Role::User->permissions())->toHaveCount(1);
});

test('role permissions are Permission enum instances', function () {
    expect(Role::Manager->permissions())->each->toBeInstanceOf(Permission::class);
});
