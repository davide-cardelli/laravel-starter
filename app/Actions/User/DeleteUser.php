<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Actions\User\Concerns\GuardsLastSuperAdmin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Delete User Action
 *
 * Deletes a user from the system.
 * Follows Action-Based Architecture pattern.
 */
class DeleteUser
{
    use GuardsLastSuperAdmin;

    /**
     * Execute the delete user action.
     *
     * Permanently deletes a user from the database.
     * This is a sensitive operation that logs at WARNING level
     * and includes success/failure tracking for audit purposes.
     *
     * @param  User  $user  The user instance to delete
     * @return bool True if deletion was successful, false otherwise
     */
    public function execute(User $user): bool
    {
        Log::warning('Deleting user', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'deleted_by' => Auth::id(),
        ]);

        $result = DB::transaction(function () use ($user): bool {
            // Deleting a super-admin removes their status entirely, so the
            // last one standing must survive (lock + count inside the
            // transaction keeps concurrent deletions honest).
            $this->abortIfLastSuperAdmin($user);

            return (bool) $user->delete();
        });

        if ($result) {
            Log::info('User deleted successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } else {
            Log::error('Failed to delete user', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        }

        return $result;
    }
}
