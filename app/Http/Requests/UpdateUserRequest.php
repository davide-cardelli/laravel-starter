<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Update User Request
 *
 * Validates data for updating an existing user.
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // UserPolicy::update is the single source of truth: it requires the
        // edit-users permission AND forbids self-editing through the admin
        // panel (which would bypass the current-password and email
        // re-verification safeguards of the profile settings flow).
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^[+]?[0-9\s\-()]+$/', 'max:25'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'roles' => ['sometimes', 'array'],
            // Constrain to the enum's roles AND scope the existence check to
            // the current guard: an unscoped exists would accept a role seeded
            // for another guard and blow up later with RoleDoesNotExist.
            'roles.*' => [
                'string',
                Rule::in(Role::values()),
                Rule::exists('roles', 'name')
                    ->where('guard_name', config()->string('auth.defaults.guard')),
            ],
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'phone' => 'phone number',
            'email' => 'email address',
            'password' => 'password',
        ];
    }
}
