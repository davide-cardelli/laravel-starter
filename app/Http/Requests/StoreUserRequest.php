<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Store User Request
 *
 * Validates data for creating a new user.
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create users') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => User::nameRules(),
            'last_name' => User::nameRules(),
            'phone' => User::phoneRules(),
            'email' => User::emailRules(),
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => Role::assignmentRules(),
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
