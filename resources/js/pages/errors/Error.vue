<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    status: number;
}

const props = defineProps<Props>();

const messages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Forbidden',
        description: 'You do not have permission to access this page.',
    },
    404: {
        title: 'Page not found',
        description: 'The page you are looking for could not be found.',
    },
    500: {
        title: 'Server error',
        description: 'Something went wrong on our end. Please try again later.',
    },
    503: {
        title: 'Service unavailable',
        description: 'We are down for maintenance. Please check back soon.',
    },
};

const error = computed(
    () =>
        messages[props.status] ?? {
            title: 'Something went wrong',
            description: 'An unexpected error occurred.',
        },
);
</script>

<template>
    <Head :title="`${status} — ${error.title}`" />

    <main
        class="flex min-h-screen flex-col items-center justify-center gap-4 bg-background p-6 text-center text-foreground"
    >
        <p class="text-7xl font-bold tracking-tight text-muted-foreground">
            {{ status }}
        </p>
        <h1 class="text-2xl font-semibold">{{ error.title }}</h1>
        <p class="max-w-md text-muted-foreground">{{ error.description }}</p>
        <Link href="/">
            <Button class="mt-2">Back to home</Button>
        </Link>
    </main>
</template>
