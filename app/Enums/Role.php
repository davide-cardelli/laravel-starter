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

    /**
     * The authority rank of this role: higher outranks lower.
     *
     * The hierarchy is what stops privilege escalation — holding the
     * 'assign roles' permission alone must never let someone hand out a role
     * above their own station.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 4,
            self::Admin => 3,
            self::Manager => 2,
            self::User => 1,
        };
    }

    /**
     * Whether a holder of this role may grant or revoke the target role.
     */
    public function canAssign(self $target): bool
    {
        // Super-admin is special-cased: only a super-admin may grant or
        // revoke it, so the top role can never be minted from below.
        if ($target === self::SuperAdmin) {
            return $this === self::SuperAdmin;
        }

        return $this->rank() >= $target->rank();
    }

    /**
     * The highest-ranked enum role among the given role names.
     *
     * Accepts plain strings (not User) so this layer stays a true leaf.
     * Unknown names — custom roles created at runtime — carry no rank and
     * are ignored.
     *
     * @param  iterable<int, string>  $roleNames
     */
    public static function highestOf(iterable $roleNames): ?self
    {
        $highest = null;

        foreach ($roleNames as $name) {
            $case = self::tryFrom($name);

            if ($case !== null && ($highest === null || $case->rank() > $highest->rank())) {
                $highest = $case;
            }
        }

        return $highest;
    }
}
