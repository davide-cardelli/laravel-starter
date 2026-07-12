<?php

declare(strict_types=1);

use App\Enums\Permission as PermissionEnum;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('super-admin holds every permission, including ones added outside the enum', function () {
    // A permission registered outside App\Enums\Permission — e.g. by a package,
    // a migration, or a downstream seeder.
    Permission::create(['name' => 'operate the reactor']);

    $this->seed(RolePermissionSeeder::class);

    expect(Role::findByName('super-admin')->hasPermissionTo('operate the reactor'))->toBeTrue();
});

test('the seeder grants super-admin exactly the enum permissions on a clean database', function () {
    $this->seed(RolePermissionSeeder::class);

    $granted = Role::findByName('super-admin')->permissions->pluck('name')->sort()->values()->all();

    expect($granted)->toEqual(collect(PermissionEnum::values())->sort()->values()->all());
});
