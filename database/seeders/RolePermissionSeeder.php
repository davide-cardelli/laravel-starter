<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role & Permission Seeder
 *
 * Seeds base roles and permissions for the application.
 * This is part of the reusable Laravel template.
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

        // Create Permissions
        $permissions = [
            // User Management
            'view users',
            'create users',
            'edit users',
            'delete users',
            'assign roles',

            // Settings
            'view settings',
            'edit settings',

            // Example: Content Management (customize for your app)
            'view content',
            'create content',
            'edit content',
            'delete content',
            'publish content',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Roles and sync their Permissions. firstOrCreate + syncPermissions
        // keep this seeder idempotent, so `composer setup` stays re-runnable.

        // Super Admin - has ALL permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin - can manage users and content
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions([
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

        // Manager - can manage content but not users
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'view users',
            'view content',
            'create content',
            'edit content',
            'publish content',
        ]);

        // User - basic access
        $user = Role::firstOrCreate(['name' => 'user']);
        $user->syncPermissions([
            'view content',
        ]);

        $this->command->info('✅ Roles and permissions seeded successfully!');
        $this->command->table(
            ['Role', 'Permissions Count'],
            [
                ['super-admin', $superAdmin->permissions->count()],
                ['admin', $admin->permissions->count()],
                ['manager', $manager->permissions->count()],
                ['user', $user->permissions->count()],
            ]
        );
    }
}
