<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import UserForm from '@/pages/admin/users/partials/UserForm.vue';
import type { BreadcrumbItem, Role } from '@/types';
import type { InertiaForm } from '@inertiajs/vue3';
import { Head, router } from '@inertiajs/vue3';

interface User {
    id: number;
    first_name: string;
    last_name: string;
    phone: string;
    name: string;
    email: string;
    roles: Array<{ id: number; name: string }>;
}

interface UserFormData {
    first_name: string;
    last_name: string;
    phone: string;
    email: string;
    password: string;
    password_confirmation: string;
    roles: string[];
}

interface Props {
    user: User;
    roles: Role[];
}

const props = defineProps<Props>();

const handleSubmit = (form: InertiaForm<UserFormData>) => {
    form.put(`/admin/users/${props.user.id}`);
};

const handleCancel = () => {
    router.visit('/admin/users');
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/admin/users' },
    { title: 'Edit' },
];
</script>

<template>
    <Head title="Edit User" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <h1 class="text-2xl font-bold">Edit User</h1>

            <UserForm
                :user="user"
                :roles="roles"
                @submit="handleSubmit"
                @cancel="handleCancel"
            />
        </div>
    </AppLayout>
</template>
