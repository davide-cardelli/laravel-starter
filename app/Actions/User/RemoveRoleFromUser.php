<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Actions\User\Concerns\GuardsLastSuperAdmin;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/**
 * Remove Role from User Action
 *
 * Removes a role from a user using Spatie Permission.
 * Follows Action-Based Architecture pattern.
 */
class RemoveRoleFromUser
{
    use GuardsLastSuperAdmin;

    /**
     * Execute the remove role action.
     *
     * @param  User  $user  The user to remove the role from
     * @param  string|Role  $role  The role name or Role model instance
     * @return User The updated user instance (refreshed from database)
     */
    public function execute(User $user, string|Role $role): User
    {
        $roleName = $role instanceof Role ? $role->name : $role;

        Log::info('Removing role from user', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $roleName,
            'removed_by' => Auth::id(),
        ]);

        DB::transaction(function () use ($user, $role, $roleName): void {
            // Only stripping the super-admin role itself endangers the
            // invariant; removing any other role from a super-admin is fine.
            if ($roleName === RoleEnum::SuperAdmin->value) {
                $this->abortIfLastSuperAdmin($user);
            }

            $user->removeRole($role);
        });

        Log::info('Role removed successfully', [
            'user_id' => $user->id,
            'role' => $roleName,
        ]);

        return $user->fresh() ?? $user;
    }
}
