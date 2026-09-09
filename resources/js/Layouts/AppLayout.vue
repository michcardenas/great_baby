<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import {
    LayoutDashboard, Package, ShoppingCart, Warehouse, Calculator,
    FileText, Users, Truck, Settings, Bell, Search, LogOut,
    Menu as MenuIcon, X, ChevronDown, Home, BarChart3, MessageSquare,
    Boxes, Camera, Scissors, Wallet, MapPin, AlertTriangle, Radio, Upload,
    Undo2, ScanLine,
} from 'lucide-vue-next';
import CommandPalette from '@/Components/CommandPalette.vue';
import CopilotoChat from '@/Components/CopilotoChat.vue';
import NotificacionesBell from '@/Components/NotificacionesBell.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
// C-QA-D-8: badge de pedidos B2B pendientes por revisar
const pedidosB2BPend = computed(() => Number(page.props.badges?.pedidos_b2b_pendientes || 0));

// Sidebar: persistente por usuario. Colapsable manualmente en desktop y móvil.
// Se recuerda el estado en localStorage.
const readSidebar = () => {
    if (typeof window === 'undefined') return true;
    const stored = localStorage.getItem('gb.sidebar.open');
    if (stored !== null) return stored === '1';
    return window.innerWidth >= 768; // default: abierto en desktop, cerrado en mobile
};
const sidebarOpen = ref(readSidebar());

// Persistir preferencia
const setSidebar = (v) => {
    sidebarOpen.value = v;
    try { localStorage.setItem('gb.sidebar.open', v ? '1' : '0'); } catch {}
};
const toggleSidebar = () => setSidebar(! sidebarOpen.value);

// Al pasar de mobile a desktop, restaurar preferencia
useEventListener(typeof window !== 'undefined' ? window : null, 'resize', () => {
    // Solo forzar cierre si venimos de desktop→mobile
    if (window.innerWidth < 768 && sidebarOpen.value) {
        const stored = localStorage.getItem('gb.sidebar.open');
        if (stored === null) sidebarOpen.value = false; // primera vez móvil = cerrado
    }
});

// Cerrar con ESC en mobile
useEventListener(typeof window !== 'undefined' ? window : null, 'keydown', (e) => {
    if (e.key === 'Escape' && sidebarOpen.value && window.innerWidth < 768) setSidebar(false);
});

// Toast global de errores con COLA (evita pisar mensajes en cascada).
import { useSonido } from '@/composables/useSonido';
const { beep: beepErr } = useSonido();
const toasts = ref([]);
let toastSeq = 0;
let ultimoBeepAt = 0; // throttle 1500ms — evita cascada auditiva en bodega
useEventListener(typeof window !== 'undefined' ? window : null, 'gb:error', (e) => {
    const id = ++toastSeq;
    toasts.value.push({ id, mensaje: e.detail?.mensaje || 'Error' });
    const ahora = Date.now();
    if (ahora - ultimoBeepAt > 1500) {
        beepErr('error');
        ultimoBeepAt = ahora;
    }
    setTimeout(() => {
        toasts.value = toasts.value.filter(t => t.id !== id);
    }, 6000);
});
const cerrarToast = (id) => { toasts.value = toasts.value.filter(t => t.id !== id); };
const openGroups = ref({ operacion: true });

const groups = [
    {
        key: 'operacion',
        label: 'Operación',
        items: [
            { name: '🌅 Cosas del día', href: '/app/cosas-del-dia', icon: BarChart3 },
            { name: '🗺 Mapa Colombia', href: '/app/mapa-colombia', icon: BarChart3 },
            { name: 'Torre de Control', href: '/app', icon: BarChart3 },
            { name: 'Estación de Empaque', href: '/app/estacion-empaque', icon: Package },
        ],
    },
    {
        key: 'cartera',
        label: 'Cartera y CRM',
        items: [
            { name: 'Dashboard cartera', href: '/app/cartera', icon: BarChart3 },
            { name: 'Facturas de venta', href: '/app/facturas', icon: FileText },
            { name: 'Contactos', href: '/app/contactos', icon: Users },
            { name: 'Pagos', href: '/app/pagos', icon: Calculator },
            { name: 'Crédito y cobranza', href: '/app/credito', icon: Calculator },
            { name: 'CRM · Segmentación', href: '/app/crm', icon: Bell },
            { name: 'Cartera · Reportes', href: '/app/cartera/reportes', icon: BarChart3 },
            { name: 'Cobranzas registros', href: '/app/cartera/cobranzas', icon: MessageSquare },
            { name: 'Solicitudes crédito', href: '/app/cartera/solicitudes', icon: FileText },
            { name: 'Movimientos contables', href: '/app/cartera/movimientos', icon: Calculator },
            { name: 'Pedidos B2B', href: '/app/pedidos-b2b', icon: Package, badge: 'pedidosB2BPend' },
        ],
    },
    {
        key: 'servicio',
        label: 'Servicio y Marketing',
        items: [
            { name: 'Garantías · tickets', href: '/app/garantias', icon: Bell },
            { name: 'Nueva garantía', href: '/app/garantias/nueva', icon: Bell },
            { name: 'Marketing · parrilla', href: '/app/marketing/parrilla', icon: BarChart3 },
        ],
    },
    {
        key: 'rrhh',
        label: 'Gestión Humana',
        items: [
            { name: 'Vacantes', href: '/app/rrhh/vacantes', icon: Users },
            { name: 'Empleados', href: '/app/rrhh/empleados', icon: Users },
        ],
    },
    {
        key: 'compras',
        label: 'Compras e Inventario',
        items: [
            { name: 'Compras · Órdenes', href: '/app/compras', icon: ShoppingCart },
            { name: 'Compras · Nueva OC', href: '/app/compras/oc/nueva', icon: ShoppingCart },
            { name: 'Compras · Reporte', href: '/app/compras/reporte', icon: BarChart3 },
            { name: 'Inventario · Stock', href: '/app/inventario', icon: Warehouse },
            { name: 'Inventario · Kardex', href: '/app/inventario/kardex', icon: Warehouse },
            { name: 'Inventario · Conteos', href: '/app/inventario/conteos', icon: Warehouse },
            { name: 'Inventario · Traslados', href: '/app/inventario/traslados', icon: Warehouse },
            { name: 'Inventario · Alertas', href: '/app/inventario/alertas', icon: Warehouse },
            { name: 'Inventario · Reporte', href: '/app/inventario/reporte-stock', icon: BarChart3 },
            { name: 'Contabilidad', href: '/app/contabilidad', icon: Calculator },
            { name: 'Contabilidad · Panel', href: '/app/contabilidad/panel', icon: Calculator },
            { name: 'Contabilidad · Reportes (9)', href: '/app/contabilidad/reportes', icon: BarChart3 },
            { name: 'Contabilidad · Detalle', href: '/app/contabilidad/reporte-detalle', icon: FileText },
        ],
    },
    {
        key: 'dropi',
        label: 'Dropi',
        items: [
            // Bodega (operativa diaria)
            { name: 'Dropi · Panel', href: '/app/dropi', icon: LayoutDashboard },
            { name: 'Vista Alistador', href: '/app/dropi/alistador', icon: Boxes },
            { name: 'Escáner cámara', href: '/app/dropi/escaner-camara', icon: Camera },
            { name: 'Registrar devolución', href: '/app/dropi/devolucion/registrar', icon: Undo2 },
            // Administración
            { name: 'Cortes', href: '/app/dropi/cortes', icon: Scissors },
            { name: 'Wallet · movimientos', href: '/app/dropi/wallet', icon: Wallet },
            { name: 'Discrepancias wallet', href: '/app/dropi/discrepancias', icon: AlertTriangle },
            { name: 'Ubicaciones inventario', href: '/app/dropi/ubicaciones', icon: MapPin },
            { name: 'Inventario en vivo', href: '/app/dropi/inventario-en-vivo', icon: Radio },
            { name: 'Reportes Dropi', href: '/app/dropi/reportes', icon: BarChart3 },
            { name: 'Importar productos', href: '/app/dropi/productos/importar', icon: Upload },
            // Catálogo (compartido)
            { name: 'Catálogo', href: '/app/catalogo', icon: Package },
            { name: 'Catálogo · Maestras', href: '/app/catalogo/maestras', icon: FileText },
        ],
    },
    {
        key: 'gerencia',
        label: 'Gerencia',
        items: [
            { name: 'Gastos y reembolsos', href: '/app/gastos', icon: Calculator },
            { name: 'Bandeja importaciones', href: '/app/bandeja-importaciones', icon: FileText },
        ],
    },
    {
        key: 'config',
        label: 'Configuración',
        items: [
            { name: 'Reglas de negocio', href: '/app/reglas', icon: Settings },
            { name: 'Empresa', href: '/app/empresa', icon: Home },
            { name: 'Plantillas PDF', href: '/app/plantillas', icon: FileText },
            { name: 'SIIGO', href: '/app/siigo', icon: Settings },
        ],
    },
];

// UX #15: usar page.url (reactivo a Inertia) en vez de window.location (estático).
const currentPath = computed(() => page.url || '/');
const isActive = (href) => href === '/app' ? currentPath.value === '/app' : currentPath.value.startsWith(href);

const toggleGroup = (key) => { openGroups.value[key] = !openGroups.value[key]; };
</script>

<template>
    <div class="min-h-screen bg-surface-50 dark:bg-surface-950 flex">
        <!-- Backdrop móvil: click cierra el sidebar -->
        <div
            v-if="sidebarOpen"
            @click="setSidebar(false)"
            aria-hidden="true"
            class="md:hidden fixed inset-0 z-30 bg-black/40 backdrop-blur-sm"
        ></div>
        <!-- Sidebar colapsable con ancho animado en desktop -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 transform transition-all duration-200 md:relative',
                'bg-white dark:bg-surface-900 border-r border-surface-200 dark:border-surface-800',
                sidebarOpen ? 'w-64 translate-x-0' : 'w-0 -translate-x-full md:w-0 md:translate-x-0',
                'overflow-hidden',
            ]"
        >
            <!-- Brand -->
            <div class="h-16 flex items-center justify-between px-4 border-b border-surface-200 dark:border-surface-800 w-64">
                <Link href="/app" class="flex items-center gap-2 font-bold text-lg">
                    <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white text-xs">
                        GB
                    </div>
                    <span class="text-surface-900 dark:text-surface-100">GREAT BABY</span>
                </Link>
                <button @click="setSidebar(false)" class="btn-ghost p-1.5" title="Cerrar menú">
                    <X class="h-5 w-5"/>
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-2 py-3 space-y-1 w-64">
                <div v-for="group in groups" :key="group.key">
                    <button
                        @click="toggleGroup(group.key)"
                        class="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400 hover:text-surface-700 dark:hover:text-surface-200"
                    >
                        <span>{{ group.label }}</span>
                        <ChevronDown :class="['h-4 w-4 transition-transform', openGroups[group.key] ? 'rotate-0' : '-rotate-90']"/>
                    </button>
                    <div v-show="openGroups[group.key]" class="mt-1 space-y-0.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            :class="[
                                'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                isActive(item.href)
                                    ? 'bg-brand-50 text-brand-800 dark:bg-brand-900/30 dark:text-brand-300'
                                    : 'text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800',
                            ]"
                        >
                            <component :is="item.icon" class="h-4 w-4 flex-shrink-0"/>
                            <span class="flex-1">{{ item.name }}</span>
                            <span v-if="item.badge === 'pedidosB2BPend' && pedidosB2BPend > 0"
                                class="ml-auto min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center animate-pulse">
                                {{ pedidosB2BPend }}
                            </span>
                        </Link>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Topbar -->
            <header class="h-16 border-b border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900 flex items-center justify-between px-4 sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <button @click="toggleSidebar" class="btn-ghost p-2" aria-label="Alternar menú lateral"
                        :title="sidebarOpen ? 'Ocultar menú' : 'Mostrar menú'">
                        <MenuIcon class="h-5 w-5"/>
                    </button>
                    <!-- MEJORAS-A · ⌘K real: buscador global funcional -->
                    <CommandPalette/>
                </div>

                <div class="flex items-center gap-2">
                    <!-- MEJORAS-B · Bell real-time con polling 20s + sonido -->
                    <NotificacionesBell/>
                    <div class="flex items-center gap-2 pl-2 border-l border-surface-200 dark:border-surface-800">
                        <div class="h-8 w-8 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white text-sm font-bold" :title="user?.name">
                            {{ user?.name?.charAt(0) ?? '?' }}
                        </div>
                        <div class="hidden md:block text-sm">
                            <div class="font-medium">{{ user?.name }}</div>
                            <div class="text-xs text-surface-500">{{ user?.es_aracely ? 'Gerencia' : (user?.roles?.[0] || 'Operario') }}</div>
                        </div>
                        <Link href="/logout" method="post" as="button" class="btn-ghost p-2" title="Cerrar sesión" aria-label="Cerrar sesión">
                            <LogOut class="h-4 w-4"/>
                        </Link>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 p-6 overflow-x-auto">
                <slot/>
            </main>
        </div>

        <!-- MEJORAS-B · Copiloto IA flotante (solo Aracely lo ve) -->
        <CopilotoChat v-if="user?.es_aracely"/>

        <!-- Toasts globales de error con COLA (varios errores no se pisan). -->
        <div class="fixed bottom-6 right-6 z-50 space-y-2 max-w-sm w-full pointer-events-none">
            <transition-group
                tag="div"
                class="space-y-2"
                enter-active-class="transition duration-200"
                enter-from-class="translate-y-4 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-for="t in toasts" :key="t.id"
                     role="alert"
                     class="pointer-events-auto px-4 py-3 rounded-lg shadow-2xl bg-red-600 text-white text-sm font-medium flex items-center gap-3">
                    <span class="text-lg" aria-hidden="true">⚠</span>
                    <span class="flex-1">{{ t.mensaje }}</span>
                    <button @click="cerrarToast(t.id)" class="text-white/70 hover:text-white text-lg" aria-label="Cerrar">×</button>
                </div>
            </transition-group>
        </div>
    </div>
</template>
