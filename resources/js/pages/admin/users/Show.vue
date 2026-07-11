<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCan } from '@/composables/useCan';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { assignRole, index, removeRole } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useHttp } from '@inertiajs/vue3';
import { ArrowLeft, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Permission {
    id: number;
    name: string;
}

interface Role {
    id: number;
    name: string;
    permissions?: Permission[];
}

interface ShowUser {
    id: number;
    first_name: string;
    last_name: string;
    phone: string;
    name: string;
    email: string;
    created_at: string;
    roles: Role[];
}

interface Props {
    user: ShowUser;
    roles: Role[];
}

const props = defineProps<Props>();

const canAssignRoles = useCan('assign roles');
const toast = useToast();

// Local copy of the user's roles so assign/remove can update the UI
// optimistically, before the server confirms.
const userRoles = ref<Role[]>([...props.user.roles]);
const selectedRoleId = ref<number | ''>('');

const availableRoles = computed(() =>
    props.roles.filter(
        (role) => !userRoles.value.some((held) => held.id === role.id),
    ),
);

const permissions = computed(() => {
    const names = userRoles.value.flatMap(
        (role) => role.permissions?.map((permission) => permission.name) ?? [],
    );

    return [...new Set(names)].sort();
});

// Standalone XHR requests (no page navigation) — Inertia 3's useHttp.
const http = useHttp<Record<string, never>, { message: string }>({});

const assign = () => {
    const role = availableRoles.value.find(
        (candidate) => candidate.id === selectedRoleId.value,
    );

    if (!role) {
        return;
    }

    userRoles.value.push(role);
    selectedRoleId.value = '';

    // useHttp's onError fires only on 422; real failures here are http
    // exceptions (403/419/500) or network errors, so the rollback lives there.
    const rollback = () => {
        userRoles.value = userRoles.value.filter((held) => held.id !== role.id);
        toast.error(`Could not assign role '${role.name}'.`);
        return false;
    };

    http.post(assignRole({ user: props.user.id, role: role.id }).url, {
        headers: { Accept: 'application/json' },
        onSuccess: (response) => toast.success(response.message),
        onHttpException: rollback,
        onNetworkError: rollback,
    }).catch(() => {
        // Errors are handled in the callbacks above; swallow the rejected
        // promise so it does not surface as an unhandled rejection.
    });
};

const remove = (role: Role) => {
    const previous = [...userRoles.value];
    userRoles.value = userRoles.value.filter((held) => held.id !== role.id);

    const rollback = () => {
        userRoles.value = previous;
        toast.error(`Could not remove role '${role.name}'.`);
        return false;
    };

    http.delete(removeRole({ user: props.user.id, role: role.id }).url, {
        headers: { Accept: 'application/json' },
        onSuccess: (response) => toast.success(response.message),
        onHttpException: rollback,
        onNetworkError: rollback,
    }).catch(() => {
        // See assign(): failures are handled by the callbacks.
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Users', href: index().url },
    { title: props.user.name },
];
</script>

<template>
    <Head :title="user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">{{ user.name }}</h1>
                <Link :href="index()">
                    <Button variant="secondary">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to users
                    </Button>
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Profile</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-2 text-sm">
                        <div class="grid grid-cols-3">
                            <span class="text-muted-foreground">Email</span>
                            <span class="col-span-2">{{ user.email }}</span>
                        </div>
                        <div class="grid grid-cols-3">
                            <span class="text-muted-foreground">Phone</span>
                            <span class="col-span-2">{{ user.phone }}</span>
                        </div>
                        <div class="grid grid-cols-3">
                            <span class="text-muted-foreground">Created</span>
                            <span class="col-span-2">
                                {{
                                    new Date(
                                        user.created_at,
                                    ).toLocaleDateString()
                                }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Roles</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="role in userRoles"
                                :key="role.id"
                                variant="secondary"
                                class="gap-1"
                            >
                                {{ role.name }}
                                <button
                                    v-if="canAssignRoles"
                                    type="button"
                                    class="hover:text-destructive"
                                    :disabled="http.processing"
                                    @click="remove(role)"
                                >
                                    <X class="h-3 w-3" />
                                    <span class="sr-only">
                                        Remove {{ role.name }}
                                    </span>
                                </button>
                            </Badge>
                            <span
                                v-if="userRoles.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                No roles
                            </span>
                        </div>

                        <div
                            v-if="canAssignRoles && availableRoles.length > 0"
                            class="flex gap-2"
                        >
                            <select
                                v-model="selectedRoleId"
                                class="flex-1 rounded-md border px-3 py-1 text-sm"
                            >
                                <option value="" disabled>
                                    Assign a role...
                                </option>
                                <option
                                    v-for="role in availableRoles"
                                    :key="role.id"
                                    :value="role.id"
                                >
                                    {{ role.name }}
                                </option>
                            </select>
                            <Button
                                size="sm"
                                :disabled="
                                    selectedRoleId === '' || http.processing
                                "
                                @click="assign"
                            >
                                <Plus class="mr-1 h-4 w-4" />
                                Assign
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Permissions via roles</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <Badge
                        v-for="permission in permissions"
                        :key="permission"
                        variant="outline"
                    >
                        {{ permission }}
                    </Badge>
                    <span
                        v-if="permissions.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No permissions
                    </span>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
