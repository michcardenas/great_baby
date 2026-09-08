<script setup>
import { ref, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Package, Search, Tag, Calculator } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    productos: { type: Object, required: true },
    filtros: { type: Object, required: true },
    maestras: { type: Object, required: true },
});

const q = ref(props.filtros.q || '');
const buscar = useDebounceFn(() => {
    router.get('/app/catalogo', { q: q.value }, { preserveScroll: true, preserveState: true, replace: true });
}, 400);
watch(q, buscar);

const fmtCOP = (n) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO');
</script>

<template>
    <Head title="Catálogo"/>
    <AppLayout>
        <div class="space-y-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Package class="h-6 w-6 text-brand-600"/>
                    Catálogo
                </h1>
                <p class="text-sm text-surface-500 mt-1">Productos, variantes, tablas maestras y sincronización SIIGO.</p>
            </div>

            <!-- Maestras summary -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 text-center">
                <div v-for="(v, k) in maestras" :key="k" class="card p-3">
                    <div class="text-2xl font-black text-brand-600">{{ v }}</div>
                    <div class="text-xs uppercase text-surface-500 capitalize">{{ k.replace('_', ' ') }}</div>
                </div>
            </div>

            <!-- Buscador -->
            <div class="relative">
                <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-surface-400"/>
                <input v-model="q" type="search" placeholder="Buscar por referencia o nombre…" class="input w-full pl-10"/>
            </div>

            <!-- Grid productos -->
            <div v-if="!productos.data.length" class="card p-12 text-center text-surface-500">
                <Package class="h-10 w-10 mx-auto opacity-40"/>
                <div class="text-sm mt-2">Sin productos.</div>
            </div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                <div v-for="p in productos.data" :key="p.id" class="card p-4 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs text-surface-500 font-mono">{{ p.referencia }}</div>
                            <div class="font-semibold text-surface-900 dark:text-surface-100 truncate">{{ p.nombre }}</div>
                            <div v-if="p.marca" class="text-xs text-surface-500 mt-1">{{ p.marca }}<span v-if="p.categoria"> · {{ p.categoria }}</span></div>
                        </div>
                        <span v-if="!p.activo" class="text-[10px] uppercase font-bold text-red-600 bg-red-50 dark:bg-red-950/30 px-1.5 py-0.5 rounded">Inactivo</span>
                    </div>
                    <div class="flex items-end justify-between mt-3">
                        <div>
                            <div class="text-xs text-surface-500">Precio prov</div>
                            <div class="text-lg font-bold text-brand-600">{{ fmtCOP(p.precio_proveedor) }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-surface-500"><Tag class="h-3 w-3 inline"/> {{ p.variantes }} var</div>
                            <div v-if="p.tiene_cta_contable" class="text-xs text-emerald-600 mt-1 flex items-center gap-1 justify-end">
                                <Calculator class="h-3 w-3"/> contable
                            </div>
                            <div v-if="p.siigo_id" class="text-xs text-blue-600 mt-1">↑ SIIGO</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="productos.data.length" class="card p-3 flex items-center justify-between text-sm">
                <div class="text-surface-500">{{ productos.from }}–{{ productos.to }} de {{ productos.total }}</div>
                <div class="flex items-center gap-1">
                    <template v-for="link in productos.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url"
                              :class="['px-2 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'hover:bg-surface-100 dark:hover:bg-surface-800']"
                              v-html="link.label"/>
                        <span v-else class="px-2 py-1 rounded text-xs text-surface-400" v-html="link.label"/>
                    </template>
                </div>
            </div>

            <div class="text-xs text-surface-500 text-center py-4">
                Para crear/editar productos usá el <a href="/admin/productos" class="text-brand-600 hover:underline">panel Filament</a> (con imágenes, variantes y pestaña contable).
            </div>
        </div>
    </AppLayout>
</template>
