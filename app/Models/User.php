<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * Without this, the computed full name is absent when a user is serialized
     * in a collection (e.g. the paginated users list), leaving the name column
     * and its link to the detail page empty.
     *
     * @var list<string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the user's full name.
     *
     * This accessor provides backwards compatibility with Laravel Fortify
     * and other packages that expect a 'name' attribute.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    /**
     * Scope the query to users matching the given search term.
     *
     * Matches the full name ("Mario Rossi"), either name part, or the
     * email, case-insensitively. LOWER(...) LIKE is portable across
     * PostgreSQL, MySQL and SQLite (ILIKE is PostgreSQL-only), and the
     * leading-wildcard %term% never used an index anyway, so nothing
     * regresses.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $needle = '%'.mb_strtolower($term).'%';

        $query->whereAny([
            DB::raw("LOWER(concat(first_name, ' ', last_name))"),
            DB::raw('LOWER(email)'),
        ], 'like', $needle);
    }
}
