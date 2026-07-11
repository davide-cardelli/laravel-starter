<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { CircleCheck, CircleX, X } from 'lucide-vue-next';

// Flash-to-toast wiring lives in app.ts (router "success" event). This
// component only renders the shared toast stack, so it is safe to remount
// per page visit without replaying messages.
const { toasts, dismiss } = useToast();
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
