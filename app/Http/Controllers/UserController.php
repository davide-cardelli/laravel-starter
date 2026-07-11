<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\User\AssignRoleToUser;
use App\Actions\User\CreateUser;
use App\Actions\User\DeleteUser;
use App\Actions\User\RemoveRoleFromUser;
use App\Actions\User\UpdateUser;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * User Controller
 *
 * Manages CRUD operations for users with role-based authorization
 * declared via #[Authorize] attributes (policy-backed).
 * Uses Action-Based Architecture for business logic.
 */
class UserController extends Controller
{
    /**
     * Display a listing of users.
     *
     * Shows paginated user list with search/filter capabilities.
     * Includes roles for each user.
     */
    #[Authorize('viewAny', User::class)]
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles')
            ->when($request->input('search'), function ($query, $search) {
                /** @var string $search */
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('role'), function ($query, $role) {
                /** @var string $role */
                $query->role($role);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $roles = Role::all();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $request->input('search'),
                'role' => $request->input('role'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new user.
     *
     * Displays user creation form with available roles.
     */
    #[Authorize('create', User::class)]
    public function create(): Response
    {
        $roles = Role::all();

        return Inertia::render('admin/users/Create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user in storage.
     *
     * Creates a new user and optionally assigns roles.
     */
    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $user = $createUser->execute($request->validated());

        // Assign roles if provided
        if ($request->has('roles')) {
            /** @var array<string> $roles */
            $roles = $request->input('roles', []);
            $user->syncRoles($roles);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified user.
     *
     * Shows user details including roles and permissions.
     */
    #[Authorize('view', 'user')]
    public function show(User $user): Response
    {
        $user->load('roles.permissions');

        return Inertia::render('admin/users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     *
     * Displays user edit form with current roles and available roles.
     */
    #[Authorize('update', 'user')]
    public function edit(User $user): Response
    {
        $user->load('roles');
        $roles = Role::all();

        return Inertia::render('admin/users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    /**
     * Update the specified user in storage.
     *
     * Updates user data and optionally syncs roles.
     */
    public function update(
        UpdateUserRequest $request,
        User $user,
        UpdateUser $updateUser
    ): RedirectResponse {
        $updateUser->execute($user, $request->validated());

        // Sync roles if provided
        if ($request->has('roles')) {
            /** @var array<string> $roles */
            $roles = $request->input('roles', []);
            $user->syncRoles($roles);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     *
     * Permanently deletes the user from the database.
     */
    #[Authorize('delete', 'user')]
    public function destroy(User $user, DeleteUser $deleteUser): RedirectResponse
    {
        $deleteUser->execute($user);

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Assign a role to the specified user.
     *
     * Adds a specific role to the user's roles.
     */
    #[Authorize('assignRole', User::class)]
    public function assignRole(
        User $user,
        Role $role,
        AssignRoleToUser $assignRoleToUser
    ): RedirectResponse {
        $assignRoleToUser->execute($user, $role);

        return back()->with('success', "Role '{$role->name}' assigned successfully.");
    }

    /**
     * Remove a role from the specified user.
     *
     * Removes a specific role from the user's roles.
     */
    #[Authorize('removeRole', User::class)]
    public function removeRole(
        User $user,
        Role $role,
        RemoveRoleFromUser $removeRoleFromUser
    ): RedirectResponse {
        $removeRoleFromUser->execute($user, $role);

        return back()->with('success', "Role '{$role->name}' removed successfully.");
    }
}
