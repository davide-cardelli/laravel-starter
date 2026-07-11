import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';

/**
 * Check whether the authenticated user holds the given permission.
 *
 * Reads the permission names shared by HandleInertiaRequests. This only
 * drives UI visibility: server-side Policies remain the authority.
 */
export function useCan(permission: string): boolean {
    const page = usePage<AppPageProps>();

    return page.props.auth.permissions.includes(permission);
}
