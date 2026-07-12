<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Permission
 *
 * The single source of truth for permission names. Backed by the exact strings
 * seeded into the database (see RolePermissionSeeder), so a typo becomes a
 * compile-time impossibility instead of a runtime failure that slips past CI.
 *
 * Spatie's permission API accepts strings, so pass `->value` at the boundary.
 */
enum Permission: string
{
    // User management
    case ViewUsers = 'view users';
    case CreateUsers = 'create users';
    case EditUsers = 'edit users';
    case DeleteUsers = 'delete users';
    case AssignRoles = 'assign roles';

    // Settings
    case ViewSettings = 'view settings';
    case EditSettings = 'edit settings';

    // Content (example domain — customize for your app)
    case ViewContent = 'view content';
    case CreateContent = 'create content';
    case EditContent = 'edit content';
    case DeleteContent = 'delete content';
    case PublishContent = 'publish content';

    /**
     * All permission values, for seeding and bulk assignment.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
