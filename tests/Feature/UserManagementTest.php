<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'view users']);
    Permission::create(['name' => 'create users']);
    Permission::create(['name' => 'edit users']);
    Permission::create(['name' => 'delete users']);
    Permission::create(['name' => 'assign roles']);

    // Create roles
    $superAdmin = Role::create(['name' => 'super-admin']);
    $superAdmin->givePermissionTo(['view users', 'create users', 'edit users', 'delete users', 'assign roles']);

    $admin = Role::create(['name' => 'admin']);
    $admin->givePermissionTo(['view users', 'create users', 'edit users']);

    Role::create(['name' => 'user']);
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
    $superAdmin = User::factory()->create();
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
    $superAdmin = User::factory()->create();
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
    $admin = User::factory()->create();
    $admin->assignRole('admin'); // has 'create users' but NOT 'assign roles'

    actingAs($admin)
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
    $admin = User::factory()->create();
    $admin->assignRole('admin'); // has 'edit users' but NOT 'assign roles'

    $targetUser = User::factory()->create();
    $targetUser->assignRole('user');

    actingAs($admin)
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
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin)
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
