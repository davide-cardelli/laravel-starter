<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/**
 * Assign Role to User Action
 *
 * Assigns a role to a user using Spatie Permission.
 * Follows Action-Based Architecture pattern.
 */
class AssignRoleToUser
{
    /**
     * Execute the assign role action.
     *
     * @param  User  $user  The user to assign the role to
     * @param  string|Role  $role  The role name or Role model instance
     * @return User The updated user instance (refreshed from database)
     */
    public function execute(User $user, string|Role $role): User
    {
        $roleName = $role instanceof Role ? $role->name : $role;

        Log::info('Assigning role to user', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $roleName,
            'assigned_by' => Auth::id(),
        ]);

        $user->assignRole($role);

        Log::info('Role assigned successfully', [
            'user_id' => $user->id,
            'role' => $roleName,
        ]);

        return $user->fresh() ?? $user;
    }
}
