<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo User Seeder
 *
 * Creates one pre-verified demo user per role, all sharing the well-known
 * password "password". Strictly a development/staging convenience:
 * DatabaseSeeder only invokes it outside production, so a forced
 * `migrate --seed` on a production box cannot plant known credentials.
 */
class DemoUserSeeder extends Seeder
{
    /**
     * Seed one demo user per role.
     *
     * updateOrCreate (keyed on email) plus the idempotent syncRoles keep
     * this seeder safely re-runnable.
     */
    public function run(): void
    {
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
                    // The 'hashed' cast on User hashes this with the configured
                    // driver — same as the factory, no manual bcrypt().
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );
            $user->syncRoles([$role]);
        }

        $this->command->info('✅ Demo users created successfully!');
        $this->command->newLine();
        $this->command->table(
            ['First Name', 'Last Name', 'Email', 'Phone', 'Role', 'Password'],
            // Derived from the single $demoUsers source (reordered to the table
            // columns) so the credentials can never drift from what was seeded.
            array_map(
                fn (array $u): array => [$u[0], $u[1], $u[3], $u[2], $u[4], 'password'],
                $demoUsers,
            ),
        );
        $this->command->newLine();
        $this->command->warn('⚠️  Remember to change these passwords!');
    }
}
