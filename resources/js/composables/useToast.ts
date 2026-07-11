import { ref } from 'vue';

export interface ToastItem {
    id: number;
    message: string;
    variant: 'success' | 'error';
}

const DISMISS_AFTER_MS = 4000;

// Module-level state: every consumer shares the same toast stack.
const toasts = ref<ToastItem[]>([]);
let nextId = 0;

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function push(message: string, variant: ToastItem['variant']): void {
    const id = nextId++;
    toasts.value.push({ id, message, variant });
    setTimeout(() => dismiss(id), DISMISS_AFTER_MS);
}

export function useToast() {
    return {
        toasts,
        dismiss,
        success: (message: string) => push(message, 'success'),
        error: (message: string) => push(message, 'error'),
    };
}
