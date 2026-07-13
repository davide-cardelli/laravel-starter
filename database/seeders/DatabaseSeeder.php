<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Database Seeder
 *
 * Seeds the baseline the application cannot run without (roles and
 * permissions) and, outside production only, the demo users.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles and permissions are real application state, safe everywhere.
        $this->call(RolePermissionSeeder::class);

        // Demo users carry well-known credentials (password: "password"), so
        // they must never reach production — `composer setup` runs
        // `migrate --seed --force`, which would otherwise happily plant them.
        if (! app()->isProduction()) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
