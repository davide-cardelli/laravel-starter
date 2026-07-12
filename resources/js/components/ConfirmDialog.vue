<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';

const { state, resolveConfirm } = useConfirm();

// `open` is controlled, so confirming closes the dialog by setting open=false
// directly (no round-trip through @update:open). @update:open only fires for
// reka-ui-driven dismissals — Escape, the overlay, or the Cancel button — which
// all resolve to false. This keeps the outcome deterministic.
const onConfirm = () => resolveConfirm(true);

const onOpenChange = (open: boolean) => {
    if (!open) {
        resolveConfirm(false);
    }
};
</script>

<template>
    <AlertDialog :open="state.open" @update:open="onOpenChange">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ state.title }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ state.description }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel data-test="confirm-dialog-cancel">
                    {{ state.cancelLabel }}
                </AlertDialogCancel>
                <Button data-test="confirm-dialog-confirm" @click="onConfirm">
                    {{ state.confirmLabel }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
