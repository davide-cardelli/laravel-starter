<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    // Seed the production roles/permissions (single source of truth).
    $this->seed(RolePermissionSeeder::class);

    // A persona that manages users but may NOT assign roles. The seeder has no
    // such role, so define it explicitly: the escalation tests rely on it, and
    // it must stay distinct from the seeder's "admin" (which does hold 'assign
    // roles').
    Role::create(['name' => 'user-manager'])
        ->givePermissionTo(['view users', 'create users', 'edit users', 'delete users']);
});

// INDEX TESTS
test('regular user cannot view users list', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->get(route('users.index'))
        ->assertStatus(403);
});

test('guest cannot view users list', function () {
    $this->get(route('users.index'))
        ->assertRedirect(route('login'));
});

// SEARCH TESTS
test('users can be searched', function (string $term, string $expectedEmail) {
    // Fixed, non-matching identity so the acting user never collides with the
    // search terms (a random factory user occasionally would, making it flaky).
    $superAdmin = User::factory()->create([
        'first_name' => 'Search',
        'last_name' => 'Actor',
        'email' => 'search.actor@example.test',
    ]);
    $superAdmin->assignRole('super-admin');

    User::factory()->create([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario.rossi@example.com',
    ]);
    User::factory()->create([
        'first_name' => 'Luigi',
        'last_name' => 'Verdi',
        'email' => 'luigi.verdi@example.com',
    ]);

    actingAs($superAdmin)
        ->get(route('users.index', ['search' => $term]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.email', $expectedEmail));
})->with([
    'first name' => ['Mario', 'mario.rossi@example.com'],
    'last name' => ['Verdi', 'luigi.verdi@example.com'],
    'email' => ['mario.rossi@', 'mario.rossi@example.com'],
    'full name' => ['Mario Rossi', 'mario.rossi@example.com'],
    'case-insensitive' => ['vErDi', 'luigi.verdi@example.com'],
]);

test('search combined with role filter only returns users matching both', function () {
    $superAdmin = User::factory()->create([
        'first_name' => 'Search',
        'last_name' => 'Actor',
        'email' => 'search.actor@example.test',
    ]);
    $superAdmin->assignRole('super-admin');

    $marioAdmin = User::factory()->create([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario.rossi@example.com',
    ]);
    $marioAdmin->assignRole('admin');

    // Matches the search but NOT the role filter: must be excluded
    $marioUser = User::factory()->create([
        'first_name' => 'Mario',
        'last_name' => 'Bianchi',
        'email' => 'mario.bianchi@example.com',
    ]);
    $marioUser->assignRole('user');

    // Matches the role filter but NOT the search: must be excluded
    $luigiAdmin = User::factory()->create([
        'first_name' => 'Luigi',
        'last_name' => 'Verdi',
        'email' => 'luigi.verdi@example.com',
    ]);
    $luigiAdmin->assignRole('admin');

    actingAs($superAdmin)
        ->get(route('users.index', ['search' => 'Mario', 'role' => 'admin']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.id', $marioAdmin->id));
});

// SHOW PAGE
test('super admin can view the user detail page', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('user');

    actingAs($superAdmin)
        ->get(route('users.show', $targetUser))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Show')
            ->where('user.id', $targetUser->id)
            ->has('user.roles', 1)
            // super-admin, admin, manager, user (seeded) + user-manager (test).
            ->has('roles', 5)
            // The "Permissions via roles" card derives from each role's
            // permissions, so they must be eager-loaded into the props.
            ->has('roles.0.permissions'));
});

test('regular user cannot view the user detail page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $targetUser = User::factory()->create();

    actingAs($user)
        ->get(route('users.show', $targetUser))
        ->assertStatus(403);
});

test('roles can be assigned and removed via the json endpoints', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $targetUser = User::factory()->create();
    $role = Role::findByName('admin');

    actingAs($superAdmin)
        ->postJson(route('users.assign-role', [$targetUser, $role]))
        ->assertOk()
        ->assertJson(['message' => "Role 'admin' assigned successfully."]);

    expect($targetUser->refresh()->hasRole('admin'))->toBeTrue();

    actingAs($superAdmin)
        ->deleteJson(route('users.remove-role', [$targetUser, $role]))
        ->assertOk()
        ->assertJson(['message' => "Role 'admin' removed successfully."]);

    expect($targetUser->refresh()->hasRole('admin'))->toBeFalse();
});

// FLASH MESSAGES
test('flash success message is shared with the destination page after a mutation', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->followingRedirects()
        ->post(route('users.store'), [
            'first_name' => 'Flash',
            'last_name' => 'User',
            'phone' => '+39 333 9999999',
            'email' => 'flash@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->where('flash.success', 'User created successfully.'));
});

// SHARED PERMISSIONS
test('shared auth props include the user permissions', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            // Super-admin is shared exactly every permission that exists — any
            // under- or over-sharing regression is caught.
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all()
                === collect(Permission::values())->sort()->values()->all()));
});

test('shared permissions for a base user are exactly its own', function () {
    $user = User::factory()->withRole('user')->create();

    actingAs($user)
        ->get(route('dashboard'))
        // The base 'user' role holds only 'view content'. Asserting the exact set
        // (not just the absence of two names) catches any leaked admin permission.
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all() === ['view content']));
});

test('the user list includes each user full name for the detail-page link', function () {
    $superAdmin = User::factory()->withoutTwoFactor()->withRole('super-admin')->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    // The name column links to the detail page, so an absent 'name' (accessor not
    // appended) would render an empty, unclickable link.
    actingAs($superAdmin)
        ->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('users.data.0.name', 'Ada Lovelace'));
});

// CREATE TESTS
test('super admin can create new user', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'first_name' => 'New',
            'last_name' => 'User',
            'phone' => '+39 333 1234567',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['user'],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    assertDatabaseHas('users', [
        'first_name' => 'New',
        'last_name' => 'User',
        'email' => 'newuser@example.com',
    ]);

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user?->hasRole('user'))->toBeTrue();
});

test('regular user cannot create new user', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->post(route('users.store'), [
            'first_name' => 'New',
            'last_name' => 'User',
            'phone' => '+39 333 1234567',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertStatus(403);

    assertDatabaseMissing('users', [
        'email' => 'newuser@example.com',
    ]);
});

// EDIT TESTS
test('super admin can update user', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $targetUser = User::factory()->create([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'phone' => '+39 333 1111111',
        'email' => 'old@example.com',
    ]);

    actingAs($superAdmin)
        ->put(route('users.update', $targetUser), [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'phone' => '+39 333 2222222',
            'email' => 'updated@example.com',
            'roles' => ['admin'],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => 'updated@example.com',
    ]);

    $targetUser->refresh();
    expect($targetUser->hasRole('admin'))->toBeTrue();
});

test('user cannot update other users', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $targetUser = User::factory()->create([
        'first_name' => 'Target',
        'last_name' => 'User',
        'phone' => '+39 333 3333333',
    ]);

    actingAs($user)
        ->put(route('users.update', $targetUser), [
            'first_name' => 'Hacked',
            'last_name' => 'Name',
            'phone' => $targetUser->phone,
            'email' => $targetUser->email,
        ])
        ->assertStatus(403);

    assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'first_name' => 'Target',
        'last_name' => 'User',
    ]);
});

// DELETE TESTS
test('super admin can delete user', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $targetUser = User::factory()->create();

    actingAs($superAdmin)
        ->delete(route('users.destroy', $targetUser))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    assertDatabaseMissing('users', [
        'id' => $targetUser->id,
    ]);
});

test('user cannot delete other users', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $targetUser = User::factory()->create();

    actingAs($user)
        ->delete(route('users.destroy', $targetUser))
        ->assertStatus(403);

    assertDatabaseHas('users', [
        'id' => $targetUser->id,
    ]);
});

test('user cannot update themselves through the admin panel', function () {
    $admin = User::factory()->create([
        'first_name' => 'Original',
    ]);
    $admin->assignRole('super-admin');

    // Self-editing via the admin endpoint would change the password without
    // current_password and the email without re-verification, so the policy
    // must reject it even for the highest role.
    actingAs($admin)
        ->put(route('users.update', $admin), [
            'first_name' => 'Hijacked',
            'last_name' => 'Self',
            'phone' => '+39 333 9999999',
            'email' => 'sneaky-new-email@example.com',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])
        ->assertStatus(403);

    assertDatabaseHas('users', [
        'id' => $admin->id,
        'first_name' => 'Original',
    ]);
});

test('user cannot delete themselves', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    actingAs($user)
        ->delete(route('users.destroy', $user))
        ->assertStatus(403);

    assertDatabaseHas('users', [
        'id' => $user->id,
    ]);
});

// VALIDATION TESTS
test('user creation requires valid email', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+39 333 1234567',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors('email');
});

test('user creation requires unique email', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+39 333 1234567',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors('email');
});

test('user creation requires password confirmation', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+39 333 1234567',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ])
        ->assertSessionHasErrors('password');
});

test('user creation requires first_name', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'last_name' => 'User',
            'phone' => '+39 333 1234567',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors('first_name');
});

test('user creation requires last_name', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'first_name' => 'Test',
            'phone' => '+39 333 1234567',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors('last_name');
});

test('user creation requires phone', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors('phone');
});

test('user creation requires email', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+39 333 1234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors('email');
});

test('user creation requires password', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+39 333 1234567',
            'email' => 'test@example.com',
        ])
        ->assertSessionHasErrors('password');
});

// UPDATE VALIDATION TESTS
test('user update requires unique email', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $targetUser = User::factory()->create([
        'email' => 'target@example.com',
    ]);

    actingAs($superAdmin)
        ->put(route('users.update', $targetUser), [
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'phone' => $targetUser->phone,
            'email' => 'existing@example.com', // Try to use existing email
        ])
        ->assertSessionHasErrors('email');

    // Verify user email wasn't changed
    $targetUser->refresh();
    expect($targetUser->email)->toBe('target@example.com');
});

test('user update allows keeping same email', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $targetUser = User::factory()->create([
        'first_name' => 'Old',
        'last_name' => 'Name',
        'phone' => '+39 333 4444444',
        'email' => 'user@example.com',
    ]);

    actingAs($superAdmin)
        ->put(route('users.update', $targetUser), [
            'first_name' => 'New',
            'last_name' => 'Name',
            'phone' => '+39 333 4444444',
            'email' => 'user@example.com', // Keep same email
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $targetUser->refresh();
    expect($targetUser->first_name)->toBe('New');
    expect($targetUser->email)->toBe('user@example.com');
});

test('user update requires valid email format', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $targetUser = User::factory()->create();

    actingAs($superAdmin)
        ->put(route('users.update', $targetUser), [
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'phone' => $targetUser->phone,
            'email' => 'invalid-email-format',
        ])
        ->assertSessionHasErrors('email');
});

// ROLE ASSIGNMENT AUTHORIZATION (privilege escalation regression)
test('user without assign roles permission cannot set roles on create', function () {
    $manager = User::factory()->create();
    $manager->assignRole('user-manager'); // has 'create users' but NOT 'assign roles'

    actingAs($manager)
        ->post(route('users.store'), [
            'first_name' => 'Sneaky',
            'last_name' => 'User',
            'phone' => '+39 333 6666666',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['super-admin'],
        ])
        ->assertStatus(403);

    assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
});

test('user without assign roles permission cannot change roles on update', function () {
    $manager = User::factory()->create();
    $manager->assignRole('user-manager'); // has 'edit users' but NOT 'assign roles'

    $targetUser = User::factory()->create();
    $targetUser->assignRole('user');

    actingAs($manager)
        ->put(route('users.update', $targetUser), [
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'phone' => $targetUser->phone,
            'email' => $targetUser->email,
            'roles' => ['super-admin'],
        ])
        ->assertStatus(403);

    $targetUser->refresh();
    expect($targetUser->hasRole('user'))->toBeTrue();
    expect($targetUser->hasRole('super-admin'))->toBeFalse();
});

test('user without assign roles permission can still create users without roles', function () {
    $manager = User::factory()->create();
    $manager->assignRole('user-manager');

    actingAs($manager)
        ->post(route('users.store'), [
            'first_name' => 'Plain',
            'last_name' => 'User',
            'phone' => '+39 333 7777777',
            'email' => 'plain@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $user = User::where('email', 'plain@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user?->roles->count())->toBe(0);
});

// Regression: the real UserForm ALWAYS submits a 'roles' field (initialized to
// the target's current role names), so a manager editing only the phone number
// still sends roles=[current]. That unchanged set must NOT trigger the 403 gate.
test('user without assign roles permission can update a user when roles are unchanged', function () {
    $manager = User::factory()->create();
    $manager->assignRole('user-manager');

    $targetUser = User::factory()->create(['phone' => '+39 333 0000000']);
    $targetUser->assignRole('user');

    actingAs($manager)
        ->put(route('users.update', $targetUser), [
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'phone' => '+39 333 1111199', // only the phone changes
            'email' => $targetUser->email,
            'roles' => ['user'], // same set the edit form ships
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $targetUser->refresh();
    expect($targetUser->phone)->toBe('+39 333 1111199');
    expect($targetUser->hasRole('user'))->toBeTrue(); // roles untouched
});

test('user without assign roles permission can create a user when roles field is empty', function () {
    $manager = User::factory()->create();
    $manager->assignRole('user-manager');

    actingAs($manager)
        ->post(route('users.store'), [
            'first_name' => 'Formy',
            'last_name' => 'User',
            'phone' => '+39 333 2222299',
            'email' => 'formy@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => [], // the create form ships an empty array, not an absent key
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    expect(User::where('email', 'formy@example.com')->first()?->roles->count())->toBe(0);
});

// ROLE RANK HIERARCHY (holding 'assign roles' must not grant roles above one's own)
test('admin cannot grant super-admin even with assign roles permission', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin'); // holds 'assign roles' but ranks below super-admin

    $targetUser = User::factory()->create();

    actingAs($admin)
        ->put(route('users.update', $targetUser), [
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'phone' => $targetUser->phone,
            'email' => $targetUser->email,
            'roles' => ['super-admin'],
        ])
        ->assertStatus(403);

    expect($targetUser->fresh()?->hasRole('super-admin'))->toBeFalse();
});

test('admin cannot grant super-admin through the inline assign endpoint', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $targetUser = User::factory()->create();
    $superAdminRole = Role::findByName('super-admin');

    actingAs($admin)
        ->post(route('users.assign-role', [$targetUser, $superAdminRole]))
        ->assertStatus(403);

    expect($targetUser->fresh()?->hasRole('super-admin'))->toBeFalse();
});

test('admin cannot revoke super-admin through the inline remove endpoint', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');
    $superAdminRole = Role::findByName('super-admin');

    actingAs($admin)
        ->delete(route('users.remove-role', [$superAdmin, $superAdminRole]))
        ->assertStatus(403);

    expect($superAdmin->fresh()?->hasRole('super-admin'))->toBeTrue();
});

test('admin can assign roles of equal or lower rank', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $targetUser = User::factory()->create();

    actingAs($admin)
        ->put(route('users.update', $targetUser), [
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'phone' => $targetUser->phone,
            'email' => $targetUser->email,
            'roles' => ['manager'],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    expect($targetUser->fresh()?->hasRole('manager'))->toBeTrue();
});

// LAST SUPER-ADMIN PROTECTION (the system must never end up with zero super-admins)
test('the last super-admin cannot be deleted', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin'); // holds 'delete users'

    $soleSuperAdmin = User::factory()->create();
    $soleSuperAdmin->assignRole('super-admin');

    actingAs($admin)
        ->delete(route('users.destroy', $soleSuperAdmin))
        ->assertStatus(403);

    assertDatabaseHas('users', ['id' => $soleSuperAdmin->id]);
});

test('the sole super-admin cannot remove their own super-admin role', function () {
    $soleSuperAdmin = User::factory()->create();
    $soleSuperAdmin->assignRole('super-admin');
    $superAdminRole = Role::findByName('super-admin');

    actingAs($soleSuperAdmin)
        ->delete(route('users.remove-role', [$soleSuperAdmin, $superAdminRole]))
        ->assertStatus(403);

    expect($soleSuperAdmin->fresh()?->hasRole('super-admin'))->toBeTrue();
});

test('a super-admin can be demoted while another super-admin remains', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $demotable = User::factory()->create();
    $demotable->assignRole('super-admin');

    actingAs($superAdmin)
        ->put(route('users.update', $demotable), [
            'first_name' => $demotable->first_name,
            'last_name' => $demotable->last_name,
            'phone' => $demotable->phone,
            'email' => $demotable->email,
            'roles' => ['admin'],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $demotable->refresh();
    expect($demotable->hasRole('super-admin'))->toBeFalse();
    expect($demotable->hasRole('admin'))->toBeTrue();
});

test('roles must be existing role names', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '+39 333 8888888',
            'email' => 'ghost@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['nonexistent-role'],
        ])
        ->assertSessionHasErrors('roles.0');

    assertDatabaseMissing('users', ['email' => 'ghost@example.com']);
});

// ROLE ASSIGNMENT EDGE CASES
test('user can be assigned multiple roles', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    actingAs($superAdmin)
        ->post(route('users.store'), [
            'first_name' => 'Multi',
            'last_name' => 'Role User',
            'phone' => '+39 333 5555555',
            'email' => 'multi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['admin', 'user'],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $user = User::where('email', 'multi@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->hasRole('user'))->toBeTrue();
    expect($user->roles->count())->toBe(2);
});

test('user roles can be changed during update', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('user');

    actingAs($superAdmin)
        ->put(route('users.update', $targetUser), [
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'phone' => $targetUser->phone,
            'email' => $targetUser->email,
            'roles' => ['admin'],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $targetUser->refresh();
    expect($targetUser->hasRole('admin'))->toBeTrue();
    expect($targetUser->hasRole('user'))->toBeFalse();
    expect($targetUser->roles->count())->toBe(1);
});

test('all user roles can be removed during update', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $targetUser = User::factory()->create();
    $targetUser->assignRole(['admin', 'user']);

    actingAs($superAdmin)
        ->put(route('users.update', $targetUser), [
            'first_name' => $targetUser->first_name,
            'last_name' => $targetUser->last_name,
            'phone' => $targetUser->phone,
            'email' => $targetUser->email,
            'roles' => [],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $targetUser->refresh();
    expect($targetUser->roles->count())->toBe(0);
});

test('user without roles parameter keeps existing roles', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('admin');

    actingAs($superAdmin)
        ->put(route('users.update', $targetUser), [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'phone' => $targetUser->phone,
            'email' => $targetUser->email,
            // No 'roles' parameter
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $targetUser->refresh();
    expect($targetUser->first_name)->toBe('Updated');
    expect($targetUser->hasRole('admin'))->toBeTrue();
});
