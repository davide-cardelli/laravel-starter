<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\User\AssignRoleToUser;
use App\Actions\User\CreateUser;
use App\Actions\User\DeleteUser;
use App\Actions\User\RemoveRoleFromUser;
use App\Actions\User\UpdateUser;
use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
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
        // Loaded once, then reused both for the role filter below and for the
        // Inertia payload — no extra query for the filter lookup.
        $roles = Role::all();

        $users = User::query()
            ->with('roles')
            ->when($request->input('search'), function ($query, $search) {
                /** @var string $search */
                $query->search($search);
            })
            ->when($request->input('role'), function ($query, $role) use ($roles) {
                /** @var string $role */
                // Resolve the filter against the already-loaded roles: passing
                // the model to the scope avoids Spatie re-querying it by name,
                // and an unknown ?role= is ignored instead of crashing the
                // scope with RoleDoesNotExist.
                $match = $roles->firstWhere('name', $role);

                if ($match !== null) {
                    $query->role($match);
                }
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

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
        // Assigning roles requires its own permission: creating users alone must
        // never grant it. Gate on an actual change (a new user starts with no
        // roles) so the create form's empty/default roles field is not rejected.
        /** @var array<int, string>|null $submittedRoles */
        $submittedRoles = $request->validated('roles');
        $roles = $this->authorizeRoleChange($request, [], $submittedRoles);

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
            'roles' => Role::with('permissions')->get(),
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
        // Changing roles requires its own permission: editing users alone must
        // never grant it. Gate on an actual change vs the user's current roles,
        // because the edit form always resubmits the existing role set.
        /** @var array<int, string>|null $submittedRoles */
        $submittedRoles = $request->validated('roles');
        /** @var array<int, string> $currentRoles */
        $currentRoles = $user->getRoleNames()->all();
        $roles = $this->authorizeRoleChange($request, $currentRoles, $submittedRoles);

        $updateUser->execute($user, $request->validated(), $roles);

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Authorize a role change and resolve which roles to sync.
     *
     * Returns the roles the calling user is allowed to apply: the submitted set
     * when they hold 'assign roles', or null (leave roles untouched) otherwise.
     * Aborts with 403 only when the submitted set differs from the current one
     * and the caller lacks 'assign roles' — an unchanged set is always allowed.
     *
     * @param  array<int, string>  $current
     * @param  array<int, string>|null  $submitted
     * @return array<int, string>|null
     */
    private function authorizeRoleChange(Request $request, array $current, ?array $submitted): ?array
    {
        // Absent roles field: nothing requested, leave the user's roles untouched.
        if ($submitted === null) {
            return null;
        }

        $canAssign = $request->user()?->can(Permission::AssignRoles->value) ?? false;

        $changed = collect($submitted)->sort()->values()->all()
            !== collect($current)->sort()->values()->all();

        if ($changed) {
            abort_unless($canAssign, 403);

            // Rank hierarchy: every role being granted or revoked (the delta
            // between the sets) must be within the caller's authority.
            $delta = array_merge(
                array_diff($submitted, $current),
                array_diff($current, $submitted),
            );
            $this->authorizeRankOver($request, ...$delta);
        }

        return $canAssign ? $submitted : null;
    }

    /**
     * Abort with 403 unless the caller outranks every given role.
     *
     * The 'assign roles' permission grants access to role management, but the
     * rank hierarchy on the Role enum bounds WHICH roles: nobody hands out a
     * role above their own station, and super-admin is only ever granted or
     * revoked by another super-admin. Custom roles created at runtime sit
     * outside the enum and carry no rank, so the permission alone governs them.
     */
    private function authorizeRankOver(Request $request, string ...$roleNames): void
    {
        /** @var iterable<int, string> $callerRoleNames */
        $callerRoleNames = $request->user()?->getRoleNames() ?? [];
        $callerHighest = RoleEnum::highestOf($callerRoleNames);

        foreach ($roleNames as $roleName) {
            $target = RoleEnum::tryFrom($roleName);

            if ($target === null) {
                continue;
            }

            abort_unless($callerHighest?->canAssign($target) ?? false, 403);
        }
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
        $this->authorizeRankOver($request, $role->name);

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
        $this->authorizeRankOver($request, $role->name);

        $removeRoleFromUser->execute($user, $role);

        $message = "Role '{$role->name}' removed successfully.";

        // XHR callers (e.g. Inertia's useHttp) get JSON instead of a redirect.
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
