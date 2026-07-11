<script setup lang="ts">
import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CircleCheck, CircleX, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface ToastItem {
    id: number;
    message: string;
    variant: 'success' | 'error';
}

const DISMISS_AFTER_MS = 4000;

const page = usePage<AppPageProps>();
const toasts = ref<ToastItem[]>([]);
let nextId = 0;

const dismiss = (id: number) => {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
};

const push = (message: string, variant: ToastItem['variant']) => {
    const id = nextId++;
    toasts.value.push({ id, message, variant });
    setTimeout(() => dismiss(id), DISMISS_AFTER_MS);
};

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) push(flash.success, 'success');
        if (flash?.error) push(flash.error, 'error');
    },
    { immediate: true },
);
</script>

<template>
    <div
        aria-live="polite"
        class="pointer-events-none fixed right-4 bottom-4 z-50 flex w-full max-w-sm flex-col gap-2"
    >
        <div
            v-for="toast in toasts"
            :key="toast.id"
            role="status"
            class="pointer-events-auto flex items-start gap-3 rounded-lg border bg-background p-4 shadow-lg"
        >
            <CircleCheck
                v-if="toast.variant === 'success'"
                class="h-5 w-5 shrink-0 text-green-600"
            />
            <CircleX v-else class="h-5 w-5 shrink-0 text-red-600" />
            <p class="flex-1 text-sm">{{ toast.message }}</p>
            <button
                type="button"
                class="text-muted-foreground hover:text-foreground"
                @click="dismiss(toast.id)"
            >
                <X class="h-4 w-4" />
                <span class="sr-only">Dismiss</span>
            </button>
        </div>
    </div>
</template>
