import '../css/app.css';
import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp, Link, Head, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// Interceptor GLOBAL: cualquier POST/PUT que devuelva 4xx/5xx dispara toast+beep
// y una alerta visual persistente. Elimina el "silencio en errores" (UX-13, UX-2).
router.on('invalid', (event) => {
    console.error('[Inertia] respuesta inválida', event.detail);
    window.dispatchEvent(new CustomEvent('gb:error', { detail: { mensaje: 'Respuesta inesperada del servidor' } }));
});
router.on('exception', (event) => {
    console.error('[Inertia] exception', event.detail?.exception);
    window.dispatchEvent(new CustomEvent('gb:error', { detail: { mensaje: 'Sin conexión con el servidor — reintentá' } }));
});
router.on('error', (event) => {
    const errs = event.detail?.errors || {};
    const first = Object.values(errs)[0];
    const msg = Array.isArray(first) ? first[0] : (typeof first === 'string' ? first : 'Hubo un error en la operación');
    window.dispatchEvent(new CustomEvent('gb:error', { detail: { mensaje: msg } }));
});

const appName = import.meta.env.VITE_APP_NAME || 'GREAT BABY · ERP';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue')
    ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .component('Link', Link)
            .component('Head', Head)
            .mount(el);
    },
    progress: {
        color: '#f59e0b',
        showSpinner: true,
    },
});
