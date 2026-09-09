<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Users, Search, Phone, Mail, MapPin, ExternalLink, Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    contactos: { type: Object, required: true },
    filtros: { type: Object, required: true },
    totales: { type: Object, required: true },
});

const q = ref(props.filtros.q || '');
const rol = ref(props.filtros.rol || 'todos');

const buscar = useDebounceFn(() => {
    router.get('/app/contactos', { q: q.value, rol: rol.value }, {
        preserveScroll: true, preserveState: true, replace: true,
    });
}, 400);

watch(q, buscar);
watch(rol, () => {
    router.get('/app/contactos', { q: q.value, rol: rol.value }, {
        preserveScroll: true, preserveState: true, replace: true,
    });
});

const tabs = computed(() => [
    { key: 'todos', label: 'Todos', count: props.totales.todos },
    { key: 'cliente', label: 'Clientes', count: props.totales.cliente },
    { key: 'b2b', label: 'B2B', count: props.totales.b2b },
    { key: 'proveedor', label: 'Proveedores', count: props.totales.proveedor },
    { key: 'empleado', label: 'Empleados', count: props.totales.empleado },
    { key: 'vendedor', label: 'Vendedores Dropi', count: props.totales.vendedor },
]);

const rolColor = (r) => ({
    'Cliente': 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    'B2B': 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    'Proveedor': 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200',
    'Empleado': 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    'Vendedor Dropi': 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
}[r] || 'bg-slate-100 text-slate-700');

const wa = (t) => t ? `https://wa.me/${(t.startsWith('57') ? t : '57' + t).replace(/\D/g, '')}` : '#';
</script>

<template>
    <Head title="Contactos"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Users class="h-6 w-6 text-brand-600"/>
                        Contactos
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">Clientes, B2B, proveedores, empleados y vendedores Dropi.</p>
                </div>
                <!-- H4 · CTA "Nuevo contacto" directo desde Index -->
                <a href="/admin/contactos/create" target="_blank" rel="noopener" class="btn-primary">
                    <Plus class="h-4 w-4"/> Nuevo contacto
                </a>
            </div>

            <!-- Tabs -->
            <div class="flex items-center gap-2 border-b border-surface-200 dark:border-surface-800 overflow-x-auto">
                <button v-for="t in tabs" :key="t.key" @click="rol = t.key"
                        :class="['px-4 py-2 text-sm font-semibold border-b-2 whitespace-nowrap transition',
                                 rol === t.key
                                 ? 'border-brand-500 text-brand-600'
                                 : 'border-transparent text-surface-500 hover:text-surface-800 dark:hover:text-surface-200']">
                    {{ t.label }} <span class="ml-1 text-xs opacity-70">({{ t.count }})</span>
                </button>
            </div>

            <!-- Buscador -->
            <div class="relative">
                <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-surface-400"/>
                <input v-model="q" type="search" placeholder="Buscar por nombre, documento, teléfono o email…" class="input w-full pl-10"/>
            </div>

            <!-- Grid -->
            <div v-if="! contactos.data.length" class="card p-12 text-center text-surface-500">
                <Users class="h-10 w-10 mx-auto opacity-40"/>
                <div class="text-sm mt-2">Sin contactos con estos criterios.</div>
            </div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <Link v-for="c in contactos.data" :key="c.id" :href="'/app/contactos/' + c.id"
                      class="card p-4 hover:shadow-md hover:border-brand-500/40 transition block border">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-surface-900 dark:text-surface-100 truncate">{{ c.nombre }}</div>
                            <div class="text-xs text-surface-500 font-mono truncate">{{ c.documento || '—' }}</div>
                        </div>
                        <span v-if="!c.activo" class="text-[10px] uppercase font-bold text-red-600 bg-red-50 dark:bg-red-950/30 px-1.5 py-0.5 rounded">Inactivo</span>
                    </div>
                    <div class="flex flex-wrap gap-1 mt-2">
                        <span v-for="r in c.roles" :key="r" :class="['text-[10px] font-bold uppercase px-1.5 py-0.5 rounded', rolColor(r)]">{{ r }}</span>
                    </div>
                    <div class="mt-3 space-y-0.5 text-xs text-surface-500">
                        <div v-if="c.telefono" class="flex items-center gap-1.5"><Phone class="h-3 w-3"/> {{ c.telefono }}</div>
                        <div v-if="c.email" class="flex items-center gap-1.5 truncate"><Mail class="h-3 w-3 flex-shrink-0"/> <span class="truncate">{{ c.email }}</span></div>
                        <div v-if="c.ciudad" class="flex items-center gap-1.5"><MapPin class="h-3 w-3"/> {{ c.ciudad }}</div>
                    </div>
                </Link>
            </div>

            <!-- Paginación -->
            <div v-if="contactos.data.length" class="card p-3 flex items-center justify-between text-sm">
                <div class="text-surface-500">{{ contactos.from }}–{{ contactos.to }} de {{ contactos.total }}</div>
                <div class="flex items-center gap-1">
                    <template v-for="link in contactos.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url"
                              :class="['px-2 py-1 rounded text-xs',
                                      link.active ? 'bg-brand-600 text-white' : 'text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800']"
                              v-html="link.label"/>
                        <span v-else class="px-2 py-1 rounded text-xs text-surface-400" v-html="link.label"/>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
