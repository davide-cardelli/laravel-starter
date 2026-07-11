import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { useToast } from './composables/useToast';
import type { AppPageProps } from './types';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Surface server flash messages as toasts. Registered once here on the real
// Inertia "success" visit event: history restores (back/forward) re-inject the
// cached props but do NOT fire "success", so stale flash is never replayed.
router.on('success', (event) => {
    const flash = (event.detail.page.props as AppPageProps).flash;
    const { success, error } = useToast();

    if (flash?.success) {
        success(flash.success);
    }

    if (flash?.error) {
        error(flash.error);
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
