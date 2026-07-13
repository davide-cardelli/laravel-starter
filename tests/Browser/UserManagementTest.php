<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Seed the production roles/permissions (single source of truth) instead of
    // hand-rolling a parallel set that could drift from the app.
    $this->seed(RolePermissionSeeder::class);
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

it('assigns a role from the form checkboxes when creating a user', function () {
    actingAsSuperAdmin();

    $manager = Role::findByName('manager');

    visit('/admin/users/create')
        ->type('#first_name', 'Margaret')
        ->type('#last_name', 'Hamilton')
        ->type('#phone', '+1 (555) 020-3040')
        ->type('#email', 'margaret.hamilton@example.test')
        ->type('#password', 'password123')
        ->type('#password_confirmation', 'password123')
        // The checkbox is a reka-ui button[role=checkbox], so clicking it must
        // flip the bound form state — this is exactly the binding under test.
        ->click('#role-'.$manager->getKey())
        ->click('[data-test="submit-user-form"]')
        ->assertSee('margaret.hamilton@example.test')
        ->assertPathIs('/admin/users');

    $created = User::where('email', 'margaret.hamilton@example.test')->firstOrFail();

    expect($created->hasRole('manager'))->toBeTrue();
});

it('unchecks a role from the form checkboxes when editing a user', function () {
    actingAsSuperAdmin();

    $target = User::factory()->withoutTwoFactor()->create();
    $target->assignRole('manager');
    $manager = Role::findByName('manager');

    visit('/admin/users/'.$target->getKey().'/edit')
        // The checkbox renders pre-checked from the server state; clicking it
        // must uncheck it so the submit syncs the role away.
        ->click('#role-'.$manager->getKey())
        ->click('[data-test="submit-user-form"]')
        ->assertPathIs('/admin/users');

    expect($target->fresh()->hasRole('manager'))->toBeFalse();
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

it('deletes a user only after confirming in the accessible dialog', function () {
    actingAsSuperAdmin();

    $target = User::factory()->withoutTwoFactor()->create([
        'email' => 'deletable@example.test',
    ]);
    $deleteButton = '[data-test="delete-user-'.$target->getKey().'"]';

    $page = visit('/admin/users')->assertSee('deletable@example.test');

    // Cancelling the dialog must keep the user.
    $page->click($deleteButton)
        ->assertSee('Delete user')
        ->click('[data-test="confirm-dialog-cancel"]');

    expect(User::whereKey($target->getKey())->exists())->toBeTrue();

    // Confirming deletes the user.
    $page->click($deleteButton)
        ->click('[data-test="confirm-dialog-confirm"]')
        ->assertDontSee('deletable@example.test');

    expect(User::whereKey($target->getKey())->exists())->toBeFalse();
});
