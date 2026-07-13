<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/composables/useCan';
import type { Role } from '@/types';
import type { InertiaForm } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface User {
    id: number;
    first_name: string;
    last_name: string;
    phone: string;
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
    user?: User;
    roles: Role[];
}

interface Emits {
    (e: 'submit', form: InertiaForm<UserFormData>): void;
    (e: 'cancel'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const canAssignRoles = useCan('assign roles');
const isEdit = computed(() => !!props.user);

const form = useForm({
    first_name: props.user?.first_name ?? '',
    last_name: props.user?.last_name ?? '',
    phone: props.user?.phone ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    roles: props.user?.roles.map((r) => r.name) ?? ([] as string[]),
});

const submit = () => {
    emit('submit', form);
};

const cancel = () => {
    emit('cancel');
};
</script>

<template>
    <form @submit.prevent="submit" class="max-w-2xl space-y-6">
        <div>
            <Label for="first_name">First Name</Label>
            <Input
                id="first_name"
                v-model="form.first_name"
                type="text"
                required
            />
            <InputError :message="form.errors.first_name" />
        </div>

        <div>
            <Label for="last_name">Last Name</Label>
            <Input
                id="last_name"
                v-model="form.last_name"
                type="text"
                required
            />
            <InputError :message="form.errors.last_name" />
        </div>

        <div>
            <Label for="phone">Phone</Label>
            <Input id="phone" v-model="form.phone" type="tel" required />
            <InputError :message="form.errors.phone" />
        </div>

        <div>
            <Label for="email">Email</Label>
            <Input id="email" v-model="form.email" type="email" required />
            <InputError :message="form.errors.email" />
        </div>

        <div>
            <Label for="password">Password</Label>
            <Input
                id="password"
                v-model="form.password"
                type="password"
                :required="!isEdit"
                :placeholder="isEdit ? 'Leave blank to keep current' : ''"
            />
            <InputError :message="form.errors.password" />
        </div>

        <div v-if="!isEdit || form.password">
            <Label for="password_confirmation">Confirm Password</Label>
            <Input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                :required="!isEdit"
            />
        </div>

        <div v-if="canAssignRoles">
            <Label>Roles</Label>
            <div class="mt-2 space-y-2">
                <div
                    v-for="role in roles"
                    :key="role.id"
                    class="flex items-center gap-2"
                >
                    <Checkbox
                        :id="`role-${role.id}`"
                        :value="role.name"
                        :model-value="form.roles.includes(role.name)"
                        @update:model-value="
                            (checked: boolean | 'indeterminate') => {
                                if (checked === true) {
                                    form.roles.push(role.name);
                                } else {
                                    form.roles = form.roles.filter(
                                        (r) => r !== role.name,
                                    );
                                }
                            }
                        "
                    />
                    <Label :for="`role-${role.id}`" class="font-normal">
                        {{ role.name }}
                    </Label>
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <Button
                type="submit"
                data-test="submit-user-form"
                :disabled="form.processing"
            >
                {{ isEdit ? 'Update User' : 'Create User' }}
            </Button>
            <Button type="button" variant="outline" @click="cancel">
                Cancel
            </Button>
        </div>
    </form>
</template>
