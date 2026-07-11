<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Database Seeder
 *
 * Seeds the database with initial data for development.
 * Creates roles, permissions, and demo users for each role.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates roles/permissions and 4 demo users (one per role).
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolePermissionSeeder::class);

        // Create demo users for each role. updateOrCreate (keyed on email) plus
        // the idempotent assignRole keep this seeder safely re-runnable.
        $this->command->info('Creating demo users...');

        $demoUsers = [
            ['Marco', 'Rossi', '+39 333 1234567', 'superadmin@example.com', 'super-admin'],
            ['Giulia', 'Bianchi', '+39 334 2345678', 'admin@example.com', 'admin'],
            ['Luca', 'Verdi', '+39 335 3456789', 'manager@example.com', 'manager'],
            ['Sara', 'Neri', '+39 336 4567890', 'user@example.com', 'user'],
        ];

        foreach ($demoUsers as [$firstName, $lastName, $phone, $email, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ],
            );
            $user->syncRoles([$role]);
        }

        $this->command->info('✅ Demo users created successfully!');
        $this->command->newLine();
        $this->command->table(
            ['First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Password'],
            [
                ['Marco', 'Rossi', 'superadmin@example.com', '+39 333 1234567', 'super-admin', 'password'],
                ['Giulia', 'Bianchi', 'admin@example.com', '+39 334 2345678', 'admin', 'password'],
                ['Luca', 'Verdi', 'manager@example.com', '+39 335 3456789', 'manager', 'password'],
                ['Sara', 'Neri', 'user@example.com', '+39 336 4567890', 'user', 'password'],
            ]
        );
        $this->command->newLine();
        $this->command->warn('⚠️  Remember to change these passwords in production!');
    }
}
