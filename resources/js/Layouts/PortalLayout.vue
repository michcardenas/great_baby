<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Store, ShoppingBag, FileText, LayoutDashboard, ShoppingCart, LogOut, Menu as MenuIcon, X } from 'lucide-vue-next';

const page = usePage();
const cliente = computed(() => page.props.auth?.cliente);
const carritoCount = ref(0);

// C-QA-D-6: al detectar cambio de cliente (tablet compartida), limpiar carrito.
// Guardamos el id del cliente logueado; si al cargar la sesión hay otro, purgar.
onMounted(() => {
    try {
        const guardado = localStorage.getItem('portal.cliente_id_activo');
        const actual = String(cliente.value?.id ?? '');
        if (guardado && guardado !== actual) {
            sessionStorage.removeItem('portal.carrito');
            carritoCount.value = 0;
        }
        if (actual) localStorage.setItem('portal.cliente_id_activo', actual);
    } catch {}
});

// Logout dispara limpieza explícita del carrito antes del POST.
const logout = () => {
    try {
        sessionStorage.removeItem('portal.carrito');
        localStorage.removeItem('portal.cliente_id_activo');
    } catch {}
    router.post('/portal/logout');
};

const refrescarCarrito = () => {
    try {
        const raw = sessionStorage.getItem('portal.carrito');
        const items = raw ? JSON.parse(raw) : [];
        carritoCount.value = Array.isArray(items) ? items.reduce((s, i) => s + (i.cantidad || 0), 0) : 0;
    } catch { carritoCount.value = 0; }
};
refrescarCarrito();
if (typeof window !== 'undefined') {
    window.addEventListener('portal:carrito-actualizado', refrescarCarrito);
    window.addEventListener('storage', refrescarCarrito);
}

const mobileOpen = ref(false);
const nav = [
    { href: '/portal', label: 'Inicio', icon: LayoutDashboard },
    { href: '/portal/catalogo', label: 'Catálogo', icon: ShoppingBag },
    { href: '/portal/pedidos', label: 'Mis pedidos', icon: FileText },
    { href: '/portal/facturas', label: 'Mis facturas', icon: FileText },
];
const currentPath = computed(() => page.url || '/portal');
const isActive = (h) => h === '/portal' ? currentPath.value === '/portal' : currentPath.value.startsWith(h);
</script>

<template>
    <div class="min-h-screen bg-surface-50 dark:bg-surface-950">
        <!-- Topbar -->
        <header class="bg-white dark:bg-surface-900 border-b border-surface-200 dark:border-surface-800 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <button @click="mobileOpen = !mobileOpen" class="md:hidden btn-ghost p-2">
                        <MenuIcon v-if="!mobileOpen" class="h-5 w-5"/>
                        <X v-else class="h-5 w-5"/>
                    </button>
                    <Link href="/portal" class="flex items-center gap-2 font-bold text-lg">
                        <div class="h-9 w-9 rounded-lg bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white text-xs">
                            GB
                        </div>
                        <span class="hidden sm:block text-surface-900 dark:text-surface-100">GREAT BABY <span class="text-brand-600">B2B</span></span>
                    </Link>
                </div>

                <nav class="hidden md:flex items-center gap-1">
                    <Link v-for="n in nav" :key="n.href" :href="n.href"
                        :class="[
                            'px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2',
                            isActive(n.href)
                                ? 'bg-brand-50 text-brand-800 dark:bg-brand-900/30 dark:text-brand-300'
                                : 'text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800',
                        ]">
                        <component :is="n.icon" class="h-4 w-4"/>
                        {{ n.label }}
                    </Link>
                </nav>

                <div class="flex items-center gap-2">
                    <Link href="/portal/carrito" class="btn-ghost p-2 relative" title="Carrito">
                        <ShoppingCart class="h-5 w-5"/>
                        <span v-if="carritoCount > 0" class="absolute -top-0.5 -right-0.5 h-5 min-w-5 px-1 rounded-full bg-brand-600 text-white text-[10px] font-bold flex items-center justify-center">
                            {{ carritoCount }}
                        </span>
                    </Link>
                    <div class="hidden md:flex items-center gap-2 pl-2 border-l border-surface-200 dark:border-surface-800 text-sm">
                        <div class="font-medium truncate max-w-40">{{ cliente?.nombre_display || cliente?.email }}</div>
                    </div>
                    <button @click="logout" class="btn-ghost p-2" title="Cerrar sesión">
                        <LogOut class="h-4 w-4"/>
                    </button>
                </div>
            </div>
            <!-- Móvil -->
            <div v-if="mobileOpen" class="md:hidden border-t border-surface-200 dark:border-surface-800 bg-white dark:bg-surface-900">
                <Link v-for="n in nav" :key="n.href" :href="n.href" @click="mobileOpen = false"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 text-sm',
                        isActive(n.href) ? 'bg-brand-50 text-brand-800' : 'text-surface-700',
                    ]">
                    <component :is="n.icon" class="h-4 w-4"/>
                    {{ n.label }}
                </Link>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-6">
            <div v-if="$page.props.flash?.success" class="mb-4 p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-800 dark:text-emerald-300 text-sm">
                {{ $page.props.flash.success }}
            </div>
            <slot/>
        </main>

        <footer class="border-t border-surface-200 dark:border-surface-800 mt-8 py-6 text-center text-xs text-surface-500">
            <div class="max-w-7xl mx-auto px-4">
                GREAT BABY S.A.S. · Portal B2B · Bucaramanga, Colombia
            </div>
        </footer>
    </div>
</template>
