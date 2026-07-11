<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Update User Action
 *
 * Updates an existing user with validated data.
 * Follows Action-Based Architecture pattern.
 */
class UpdateUser
{
    /**
     * Execute the update user action.
     *
     * Updates an existing user with the provided validated data and, when
     * roles are given, synchronizes them within the same database
     * transaction so the profile and role changes apply atomically.
     * Only updates the password if provided in the data array.
     * Logs detailed change information for audit tracking.
     *
     * @param  User  $user  The user instance to update
     * @param  array<string, mixed>  $data  The validated user data (first_name, last_name, phone, email, password)
     * @param  array<int, string>|null  $roles  Role names to sync; null leaves roles untouched
     * @return User The updated user instance (refreshed from database)
     */
    public function execute(User $user, array $data, ?array $roles = null): User
    {
        Log::info('Updating user', [
            'user_id' => $user->id,
            'email' => $user->email,
            'updated_by' => Auth::id(),
            'changes' => [
                'first_name' => $data['first_name'] !== $user->first_name,
                'last_name' => $data['last_name'] !== $user->last_name,
                'phone' => $data['phone'] !== $user->phone,
                'email' => $data['email'] !== $user->email,
                'password' => ! empty($data['password']),
                'roles' => $roles !== null,
            ],
        ]);

        $updateData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
        ];

        // Only update password if provided
        if (! empty($data['password'])) {
            /** @var string $password */
            $password = $data['password'];
            $updateData['password'] = Hash::make($password);
        }

        DB::transaction(function () use ($user, $updateData, $roles): void {
            $user->update($updateData);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }
        });

        Log::info('User updated successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ]);

        return $user->fresh() ?? $user;
    }
}
