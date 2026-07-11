<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Create User Action
 *
 * Creates a new user with validated data.
 * Follows Action-Based Architecture pattern.
 */
class CreateUser
{
    /**
     * Execute the create user action.
     *
     * Creates a new user with the provided validated data and, when roles
     * are given, synchronizes them within the same database transaction so
     * a user is never persisted with a partially applied role set.
     * Hashes the password before storage and logs the operation
     * for monitoring and audit purposes.
     *
     * @param  array<string, mixed>  $data  The validated user data (first_name, last_name, phone, email, password)
     * @param  array<int, string>|null  $roles  Role names to assign; null leaves roles untouched
     * @return User The newly created user instance
     */
    public function execute(array $data, ?array $roles = null): User
    {
        Log::info('Creating new user', [
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'roles' => $roles,
            'created_by' => Auth::id(),
        ]);

        /** @var string $password */
        $password = $data['password'];

        $user = DB::transaction(function () use ($data, $password, $roles): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'password' => Hash::make($password),
            ]);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            return $user;
        });

        Log::info('User created successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'roles' => $roles,
        ]);

        return $user;
    }
}
