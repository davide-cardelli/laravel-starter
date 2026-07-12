<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (['view users', 'create users', 'edit users', 'delete users', 'assign roles'] as $permission) {
        Permission::create(['name' => $permission]);
    }

    Role::create(['name' => 'super-admin'])->givePermissionTo(Permission::all());
    Role::create(['name' => 'manager']);
    Role::create(['name' => 'user']);
});

/**
 * A signed-in super-admin. actingAs() runs before visit(), so the plugin
 * injects the session cookie into the browser's requests.
 */
function actingAsSuperAdmin(): User
{
    $admin = User::factory()->withoutTwoFactor()->create();
    $admin->assignRole('super-admin');

    actingAs($admin);

    return $admin;
}

it('creates a user through the form and lists it', function () {
    actingAsSuperAdmin();

    visit('/admin/users/create')
        ->type('#first_name', 'Ada')
        ->type('#last_name', 'Lovelace')
        ->type('#phone', '+1 (555) 010-2030')
        ->type('#email', 'ada.lovelace@example.test')
        ->type('#password', 'password123')
        ->type('#password_confirmation', 'password123')
        ->click('[data-test="submit-user-form"]')
        // assertSee waits for the redirect to render the index before we assert
        // the path, so the async Inertia submit has time to complete.
        ->assertSee('ada.lovelace@example.test')
        ->assertPathIs('/admin/users');

    expect(User::where('email', 'ada.lovelace@example.test')->exists())->toBeTrue();
});

it('assigns a role inline and persists it on the server', function () {
    actingAsSuperAdmin();

    $target = User::factory()->withoutTwoFactor()->create();
    $manager = Role::findByName('manager');

    visit('/admin/users/'.$target->getKey())
        ->select('[data-test="assign-role-select"]', (string) $manager->getKey())
        ->click('[data-test="assign-role-button"]')
        // The success toast only fires once the server confirms, so waiting for
        // it means the round-trip finished before we assert the database.
        ->assertSee("Role 'manager' assigned successfully.");

    expect($target->fresh()->hasRole('manager'))->toBeTrue();
});

it('rolls back the optimistic badge when the assignment fails', function () {
    actingAsSuperAdmin();

    $target = User::factory()->withoutTwoFactor()->create();
    $manager = Role::findByName('manager');

    // Force the page to render with the assign UI present (visit() is lazy, so
    // an assertion here executes the navigation before we mutate server state).
    $page = visit('/admin/users/'.$target->getKey())
        ->assertPresent('[data-test="assign-role-button"]');

    // Now simulate the permission being revoked after the page rendered: the
    // next assign request 403s, which useHttp surfaces as an http exception, so
    // the optimistic badge must roll back and an error toast must appear.
    Role::findByName('super-admin')->revokePermissionTo('assign roles');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $page->select('[data-test="assign-role-select"]', (string) $manager->getKey())
        ->click('[data-test="assign-role-button"]')
        ->assertSee("Could not assign role 'manager'.");

    expect($target->fresh()->hasRole('manager'))->toBeFalse();
});
