<?php

namespace Database\Factories;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\RecoveryCode;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * Two-factor authentication is OFF by default: a plain user does not have
     * it. Opt in with the withTwoFactor() state, which sets a real TOTP secret.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return array{
     *     first_name: string,
     *     last_name: string,
     *     phone: string,
     *     email: string,
     *     email_verified_at: Carbon,
     *     password: string,
     *     remember_token: string,
     *     two_factor_secret: null,
     *     two_factor_recovery_codes: null,
     *     two_factor_confirmed_at: null
     * }
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('+## (###) ###-####'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= 'password',
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model does not have two-factor authentication configured.
     *
     * Redundant with the default, but kept so intent reads explicitly at the
     * call site.
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Enable two-factor authentication with a real, valid TOTP secret (not a
     * random string), so tests can generate genuine one-time codes against it.
     */
    public function withTwoFactor(): static
    {
        return $this->state(function (array $attributes): array {
            $secret = app(Google2FA::class)->generateSecretKey();

            return [
                'two_factor_secret' => encrypt($secret),
                'two_factor_recovery_codes' => encrypt(json_encode(
                    Collection::times(8, fn (): string => RecoveryCode::generate())->all()
                )),
                'two_factor_confirmed_at' => now(),
            ];
        });
    }

    /**
     * Assign the super-admin role after creation (requires roles to be seeded).
     */
    public function superAdmin(): static
    {
        return $this->withRole(RoleEnum::SuperAdmin);
    }

    /**
     * Assign the given role after creation (requires the role to exist).
     */
    public function withRole(RoleEnum|string $role): static
    {
        $name = $role instanceof RoleEnum ? $role->value : $role;

        return $this->afterCreating(fn (User $user) => $user->assignRole($name));
    }
}
