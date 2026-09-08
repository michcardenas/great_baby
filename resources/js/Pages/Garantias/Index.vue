<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ShieldCheck, Plus, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    tickets: { type: Object, required: true },
    conteos: { type: Object, required: true },
    filtros: { type: Object, required: true },
});

const q = ref(props.filtros.q || '');
let deb;
const buscar = () => {
    clearTimeout(deb);
    deb = setTimeout(() => router.get('/app/garantias', { q: q.value, estado: props.filtros.estado }, { preserveState: true, replace: true }), 300);
};

const filtrarEstado = (e) => router.get('/app/garantias', { estado: e || null, q: props.filtros.q }, { preserveState: true });

const tabs = [
    { key: '', label: 'Todos' },
    { key: 'abierto', label: 'Abiertos' },
    { key: 'aprobada', label: 'Aprobadas' },
    { key: 'en_reposicion', label: 'En reposición' },
    { key: 'rechazada', label: 'Rechazadas' },
    { key: 'cerrada', label: 'Cerradas' },
];

const badge = (e) => ({
    abierto: 'bg-blue-100 text-blue-800',
    en_revision: 'bg-amber-100 text-amber-800',
    aprobada: 'bg-emerald-100 text-emerald-800',
    en_reposicion: 'bg-brand-100 text-brand-800',
    rechazada: 'bg-red-100 text-red-800',
    cerrada: 'bg-surface-200 text-surface-600',
}[e] || 'bg-surface-100');
</script>

<template>
    <Head title="Garantías"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ShieldCheck class="h-6 w-6 text-brand-600"/>
                        Servicio al Cliente · Garantías
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">Tickets, decisiones y reposiciones a valor $0.</p>
                </div>
                <Link href="/app/garantias/nueva" class="btn-primary"><Plus class="h-4 w-4"/> Nueva garantía</Link>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">{{ $page.props.flash.success }}</div>

            <div class="card p-3 flex items-center gap-2 flex-wrap">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-500"/>
                    <input v-model="q" @input="buscar" placeholder="Buscar por número, cliente o teléfono…" class="input w-full pl-10"/>
                </div>
                <button v-for="t in tabs" :key="t.key" @click="filtrarEstado(t.key)"
                    :class="['px-3 py-1.5 text-sm rounded-lg',
                        (filtros.estado || '') === t.key ? 'bg-brand-600 text-white' : 'bg-surface-100 hover:bg-surface-200']">
                    {{ t.label }}<span v-if="conteos[t.key]" class="ml-1 text-xs opacity-80">({{ conteos[t.key] }})</span>
                </button>
            </div>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">Número</th>
                            <th class="text-left p-3">Cliente</th>
                            <th class="text-left p-3">Descripción</th>
                            <th class="text-right p-3">Cant</th>
                            <th class="text-left p-3">Creado</th>
                            <th class="text-center p-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-surface-50">
                            <td class="p-3 font-mono font-bold">
                                <Link :href="`/app/garantias/${t.id}`" class="text-brand-600 hover:underline">{{ t.numero }}</Link>
                            </td>
                            <td class="p-3">
                                <div class="font-medium">{{ t.cliente }}</div>
                                <div class="text-xs text-surface-500">{{ t.telefono }}</div>
                            </td>
                            <td class="p-3 text-xs max-w-md truncate" :title="t.descripcion">{{ t.descripcion }}</td>
                            <td class="p-3 text-right font-bold">{{ t.cantidad }}</td>
                            <td class="p-3 text-xs">{{ t.creado }}</td>
                            <td class="p-3 text-center">
                                <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(t.estado)]">{{ t.estado }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
