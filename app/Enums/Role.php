<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Role
 *
 * The single source of truth for role names, backed by the exact strings seeded
 * into the database. Each role also owns the set of permissions it grants, so
 * the seeder and the authorization model never drift apart.
 *
 * Spatie's role API accepts strings, so pass `->value` at the boundary.
 */
enum Role: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case User = 'user';

    /**
     * The permissions granted to this role.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin, self::Admin => Permission::cases(),
            self::Manager => [
                Permission::ViewUsers,
                Permission::ViewContent,
                Permission::CreateContent,
                Permission::EditContent,
                Permission::PublishContent,
            ],
            self::User => [
                Permission::ViewContent,
            ],
        };
    }

    /**
     * All role values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
