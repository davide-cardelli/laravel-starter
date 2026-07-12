<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * User Policy
 *
 * Defines authorization logic for User management operations.
 * Uses Spatie Permission package for role-based access control.
 */
class UserPolicy
{
    /**
     * Determine if the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewUsers->value);
    }

    /**
     * Determine if the user can view a specific user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::ViewUsers->value);
    }

    /**
     * Determine if the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::CreateUsers->value);
    }

    /**
     * Determine if the user can update the given user.
     */
    public function update(User $user, User $model): bool
    {
        // Users can't edit themselves through admin panel (use profile settings instead)
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can(Permission::EditUsers->value);
    }

    /**
     * Determine if the user can delete the given user.
     */
    public function delete(User $user, User $model): bool
    {
        // Users can't delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can(Permission::DeleteUsers->value);
    }

    /**
     * Determine if the user can assign roles to other users.
     */
    public function assignRole(User $user): bool
    {
        return $user->can(Permission::AssignRoles->value);
    }

    /**
     * Determine if the user can remove roles from other users.
     */
    public function removeRole(User $user): bool
    {
        return $user->can(Permission::AssignRoles->value); // Same permission as assign
    }

    /**
     * Determine if the user can restore the given user.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->can(Permission::DeleteUsers->value);
    }

    /**
     * Determine if the user can permanently delete the given user.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->can(Permission::DeleteUsers->value);
    }
}
