<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user !== null, 'User must be authenticated');

        return [
            'first_name' => User::nameRules(),
            'last_name' => User::nameRules(),
            'phone' => User::phoneRules(),
            'email' => User::emailRules($user->id),
        ];
    }
}
