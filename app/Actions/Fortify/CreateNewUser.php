<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => User::nameRules(),
            'last_name' => User::nameRules(),
            'phone' => User::phoneRules(),
            'email' => User::emailRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'phone' => $input['phone'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // Self-registered accounts start at the bottom of the hierarchy,
        // consistent with every other creation path (the admin forms assign
        // roles explicitly). Requires the seeded roles (composer setup).
        $user->assignRole(Role::User->value);

        return $user;
    }
}
