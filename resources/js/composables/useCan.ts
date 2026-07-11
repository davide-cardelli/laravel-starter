import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

/**
 * Check whether the authenticated user holds the given permission.
 *
 * Returns a reactive computed so gated UI reacts when the shared permissions
 * change (e.g. after a partial reload) without a remount. This only drives UI
 * visibility: server-side Policies remain the authority.
 */
export function useCan(permission: string): ComputedRef<boolean> {
    const page = usePage<AppPageProps>();

    return computed(() => page.props.auth.permissions.includes(permission));
}
