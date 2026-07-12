import { ref } from 'vue';

export interface ConfirmOptions {
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
}

interface ConfirmState {
    open: boolean;
    title: string;
    description: string;
    confirmLabel: string;
    cancelLabel: string;
}

// Module-level state: a single <ConfirmDialog /> (mounted in the layout) renders
// whatever confirm() requests, so any component can await a confirmation.
const state = ref<ConfirmState>({
    open: false,
    title: '',
    description: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
});

let resolver: ((value: boolean) => void) | null = null;

/**
 * Open an accessible confirmation dialog and resolve to the user's choice.
 */
function confirm(options: ConfirmOptions): Promise<boolean> {
    state.value = {
        open: true,
        title: options.title,
        description: options.description,
        confirmLabel: options.confirmLabel ?? 'Confirm',
        cancelLabel: options.cancelLabel ?? 'Cancel',
    };

    return new Promise<boolean>((resolve) => {
        resolver = resolve;
    });
}

/**
 * Settle the pending confirmation and close the dialog. Called by ConfirmDialog.
 */
function resolveConfirm(result: boolean): void {
    resolver?.(result);
    resolver = null;
    state.value = { ...state.value, open: false };
}

export function useConfirm() {
    return { state, confirm, resolveConfirm };
}
