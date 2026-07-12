<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCan } from '@/composables/useCan';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, destroy, edit, index, show } from '@/routes/users';
import type { BreadcrumbItem, Role } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface User {
    id: number;
    first_name: string;
    last_name: string;
    phone: string;
    name: string;
    email: string;
    roles: Array<{ name: string }>;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    users: {
        data: User[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
    };
    roles: Role[];
    filters: {
        search?: string;
        role?: string;
    };
}

const props = defineProps<Props>();

const canCreateUsers = useCan('create users');
const canEditUsers = useCan('edit users');
const canDeleteUsers = useCan('delete users');

const search = ref(props.filters.search || '');
const roleFilter = ref(props.filters.role || '');

const performSearch = () => {
    router.get(
        index().url,
        { search: search.value, role: roleFilter.value },
        { preserveState: true },
    );
};

const { confirm } = useConfirm();

const deleteUser = async (user: User) => {
    const confirmed = await confirm({
        title: 'Delete user',
        description: `Delete ${user.name}? This action cannot be undone.`,
        confirmLabel: 'Delete',
    });

    if (confirmed) {
        router.delete(destroy(user.id).url);
    }
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users' },
];
</script>

<template>
    <Head title="User Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">User Management</h1>
                <Link v-if="canCreateUsers" :href="create()">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Add User
                    </Button>
                </Link>
            </div>

            <div class="flex gap-4">
                <Input
                    v-model="search"
                    placeholder="Search users..."
                    @keyup.enter="performSearch"
                    class="max-w-sm"
                />
                <select
                    v-model="roleFilter"
                    @change="performSearch"
                    class="rounded-md border px-3"
                >
                    <option value="">All Roles</option>
                    <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="role.name"
                    >
                        {{ role.name }}
                    </option>
                </select>
                <Button @click="performSearch" variant="secondary">
                    Search
                </Button>
            </div>

            <div class="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Roles</TableHead>
                            <TableHead>Created</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users.data" :key="user.id">
                            <TableCell class="font-medium">
                                <Link
                                    :href="show(user.id)"
                                    class="hover:underline"
                                >
                                    {{ user.name }}
                                </Link>
                            </TableCell>
                            <TableCell>{{ user.email }}</TableCell>
                            <TableCell>
                                <Badge
                                    v-for="role in user.roles"
                                    :key="role.name"
                                    variant="secondary"
                                    class="mr-1"
                                >
                                    {{ role.name }}
                                </Badge>
                                <span
                                    v-if="user.roles.length === 0"
                                    class="text-muted-foreground"
                                >
                                    No roles
                                </span>
                            </TableCell>
                            <TableCell>
                                {{
                                    new Date(
                                        user.created_at,
                                    ).toLocaleDateString()
                                }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        v-if="canEditUsers"
                                        :href="edit(user.id)"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            :aria-label="`Edit ${user.name}`"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                    </Link>
                                    <Button
                                        v-if="canDeleteUsers"
                                        variant="ghost"
                                        size="sm"
                                        :aria-label="`Delete ${user.name}`"
                                        :data-test="`delete-user-${user.id}`"
                                        @click="deleteUser(user)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <Pagination :links="users.links" :last-page="users.last_page" />
        </div>
    </AppLayout>
</template>
