<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role & Permission Seeder
 *
 * Seeds base roles and permissions for the application from the App\Enums
 * definitions, which are the single source of truth. This is part of the
 * reusable Laravel template.
 *
 * Run with: php artisan db:seed --class=RolePermissionSeeder
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create every permission declared in the enum.
        foreach (PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value]);
        }

        // Create each role and sync the permissions it owns (defined on the enum).
        // firstOrCreate + syncPermissions keep this seeder idempotent, so
        // `composer setup` stays re-runnable.
        $summary = [];

        foreach (RoleEnum::cases() as $roleCase) {
            $role = Role::firstOrCreate(['name' => $roleCase->value]);

            // Super-admin holds every permission on its own guard — including any
            // added outside the enum (a migration, a package, a downstream seeder)
            // — so it stays omnipotent. Filtering by guard avoids Spatie's
            // GuardDoesNotMatch when the app also registers permissions on another
            // guard (e.g. 'api'). Other roles get exactly their enum-declared set.
            $permissions = $roleCase === RoleEnum::SuperAdmin
                ? Permission::where('guard_name', $role->guard_name)->get()
                : array_map(
                    fn (PermissionEnum $permission): string => $permission->value,
                    $roleCase->permissions(),
                );

            $role->syncPermissions($permissions);

            $summary[] = [$roleCase->value, $role->permissions->count()];
        }

        $this->command->info('✅ Roles and permissions seeded successfully!');
        $this->command->table(['Role', 'Permissions Count'], $summary);
    }
}
