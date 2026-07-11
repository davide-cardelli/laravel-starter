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
use Illuminate\Http\JsonResponse;
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
                $query->search($search);
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
        // Assigning roles requires its own permission: creating users alone must never grant it.
        abort_if($request->has('roles') && ! $request->user()?->can('assign roles'), 403);

        /** @var array<int, string>|null $roles */
        $roles = $request->validated('roles');

        $createUser->execute($request->validated(), $roles);

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
            'roles' => Role::all(),
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
        // Changing roles requires its own permission: editing users alone must never grant it.
        abort_if($request->has('roles') && ! $request->user()?->can('assign roles'), 403);

        /** @var array<int, string>|null $roles */
        $roles = $request->validated('roles');

        $updateUser->execute($user, $request->validated(), $roles);

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
        Request $request,
        User $user,
        Role $role,
        AssignRoleToUser $assignRoleToUser
    ): JsonResponse|RedirectResponse {
        $assignRoleToUser->execute($user, $role);

        $message = "Role '{$role->name}' assigned successfully.";

        // XHR callers (e.g. Inertia's useHttp) get JSON instead of a redirect.
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * Remove a role from the specified user.
     *
     * Removes a specific role from the user's roles.
     */
    #[Authorize('removeRole', User::class)]
    public function removeRole(
        Request $request,
        User $user,
        Role $role,
        RemoveRoleFromUser $removeRoleFromUser
    ): JsonResponse|RedirectResponse {
        $removeRoleFromUser->execute($user, $role);

        $message = "Role '{$role->name}' removed successfully.";

        // XHR callers (e.g. Inertia's useHttp) get JSON instead of a redirect.
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
