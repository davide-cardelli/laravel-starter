<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('database seeding is idempotent and safely re-runnable', function () {
    // The second run reproduces `composer setup` being executed twice; on the
    // old create()-based seeders this threw a duplicate-key error.
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Role::count())->toBe(4);
    expect(Permission::count())->toBe(12);
    expect(User::where('email', 'superadmin@example.com')->count())->toBe(1);
    expect(User::where('email', 'superadmin@example.com')->first()?->hasRole('super-admin'))->toBeTrue();
});

test('production seeding creates roles but no demo users', function () {
    // `migrate --seed --force` on a production box must never plant the
    // well-known demo credentials; roles and permissions remain required.
    // --force mirrors `composer setup` and skips the production prompt.
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(Role::count())->toBe(4);
    expect(Permission::count())->toBe(12);
    expect(User::count())->toBe(0);
});
