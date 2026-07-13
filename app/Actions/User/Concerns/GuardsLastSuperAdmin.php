<?php

declare(strict_types=1);

namespace App\Actions\User\Concerns;

use App\Enums\Role;
use App\Models\User;

/**
 * Guards the Last Super-Admin
 *
 * An operation that would leave the system with zero super-admins must abort,
 * otherwise nobody could manage roles or users anymore. Shared by the actions
 * that can strip super-admin status: delete, role removal, and role sync.
 */
trait GuardsLastSuperAdmin
{
    /**
     * Abort when removing this user's super-admin status would leave none.
     *
     * MUST run inside a database transaction: aggregates cannot take
     * FOR UPDATE, so we lock every super-admin row and count in PHP. Two
     * concurrent removals then serialize — the second waits on the row locks,
     * re-reads the survivors, and aborts if it would empty the set.
     *
     * @param  User  $user  The user whose super-admin status is being removed
     */
    protected function abortIfLastSuperAdmin(User $user): void
    {
        if (! $user->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        $superAdminIds = User::role(Role::SuperAdmin->value)
            ->lockForUpdate()
            ->pluck('id');

        abort_if(
            $superAdminIds->count() <= 1,
            403,
            'Cannot remove the last super-admin.'
        );
    }
}
