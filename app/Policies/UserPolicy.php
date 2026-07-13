<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
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

        // Rank guard: editing a user resets their password/email, so a lower-ranked
        // actor must not edit one who outranks them — otherwise an admin could reset
        // a super-admin's credentials and take the account over. Mirrors the rank
        // hierarchy already enforced on role assignment.
        if (! $this->canManageRank($user, $model)) {
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

        // Rank guard: deleting an account is at least as destructive as stripping
        // its roles (which IS rank-protected), so a lower-ranked actor must not be
        // able to delete one who outranks them — e.g. an admin annihilating a
        // super-admin.
        if (! $this->canManageRank($user, $model)) {
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
     * Whether the actor's rank authority covers the target user.
     *
     * A super-admin may only be managed by another super-admin — the one hard
     * rank invariant (mirroring Role::canAssign's special-casing), and the gap
     * that let a mere admin reset a super-admin's credentials or delete the
     * account. Roles below super-admin do not bound account edits: the
     * permission check governs them, and — unlike the four enum roles — custom
     * runtime roles carry no rank to compare, so treating a custom-role actor
     * as "rankless" must not lock them out of managing ordinary users.
     */
    private function canManageRank(User $actor, User $target): bool
    {
        if ($target->hasRole(Role::SuperAdmin->value)) {
            return $actor->hasRole(Role::SuperAdmin->value);
        }

        return true;
    }
}
