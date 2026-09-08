<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Package, RefreshCw } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    pedidos: { type: Object, required: true },
    conteos: { type: Object, required: true },
    estado_filtro: { type: String, default: null },
});

const money = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0);
const badgeEstado = (e) => ({
    borrador: 'bg-surface-100 text-surface-700',
    enviado: 'bg-blue-100 text-blue-800',
    aprobado: 'bg-emerald-100 text-emerald-800',
    rechazado: 'bg-red-100 text-red-800',
    facturado: 'bg-brand-100 text-brand-800',
    anulado: 'bg-surface-200 text-surface-600',
}[e] || 'bg-surface-100');

const cargando = ref(false);
const filtrar = (e) => router.get('/app/pedidos-b2b', { estado: e || null }, {
    preserveState: true,
    onStart: () => cargando.value = true,
    onFinish: () => cargando.value = false,
});

// C-QA-D-8: auto-refresh cada 30s para que los nuevos B2B aparezcan sin acción manual.
let intervalId;
onMounted(() => {
    intervalId = setInterval(() => {
        router.reload({ only: ['pedidos', 'conteos', 'badges'], preserveScroll: true });
    }, 30000);
});
onBeforeUnmount(() => intervalId && clearInterval(intervalId));

const tabs = [
    { key: '', label: 'Todos' },
    { key: 'enviado', label: 'Nuevos' },
    { key: 'aprobado', label: 'Aprobados' },
    { key: 'facturado', label: 'Facturados' },
    { key: 'rechazado', label: 'Rechazados' },
];
</script>

<template>
    <Head title="Pedidos B2B"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Package class="h-6 w-6 text-brand-600"/>
                        Pedidos B2B recibidos
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">Pedidos enviados por clientes desde el Portal.</p>
                </div>
                <div v-if="Number(conteos.enviado) > 0" class="px-3 py-2 rounded-lg bg-blue-500/15 text-blue-800 dark:text-blue-300 text-sm font-semibold">
                    {{ Number(conteos.enviado) }} nuevo{{ Number(conteos.enviado) > 1 ? 's' : '' }} por revisar
                </div>
            </div>

            <div class="card p-3 flex items-center gap-2 flex-wrap">
                <button v-for="t in tabs" :key="t.key" @click="filtrar(t.key)"
                    :class="['px-3 py-1.5 text-sm rounded-lg',
                        (estado_filtro || '') === t.key ? 'bg-brand-600 text-white' : 'bg-surface-100 dark:bg-surface-800 hover:bg-surface-200']">
                    {{ t.label }}
                    <span v-if="Number(conteos[t.key])" class="ml-1 text-xs opacity-80">({{ Number(conteos[t.key]) }})</span>
                </button>
            </div>

            <div v-if="!pedidos.data.length" class="card p-12 text-center text-surface-500">
                Sin pedidos {{ estado_filtro ? 'en ese estado' : '' }}.
            </div>
            <div v-else :class="['card overflow-x-auto transition-opacity', cargando ? 'opacity-50' : '']">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b border-surface-200 dark:border-surface-800">
                        <tr>
                            <th class="text-left p-3">Número</th>
                            <th class="text-left p-3">Cliente</th>
                            <th class="text-left p-3">Lista</th>
                            <th class="text-left p-3">Fecha</th>
                            <th class="text-right p-3">Total</th>
                            <th class="text-center p-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                        <tr v-for="p in pedidos.data" :key="p.id" class="hover:bg-surface-50 dark:hover:bg-surface-800">
                            <td class="p-3 font-bold">
                                <Link :href="`/app/pedidos-b2b/${p.id}`" class="text-brand-600 hover:underline">{{ p.numero }}</Link>
                            </td>
                            <td class="p-3">
                                <div class="font-medium">{{ p.cliente }}</div>
                                <div class="text-xs text-surface-500">{{ p.email }}</div>
                            </td>
                            <td class="p-3 text-xs">{{ p.lista || '—' }}</td>
                            <td class="p-3 text-xs">{{ p.fecha }}</td>
                            <td class="p-3 text-right font-bold">{{ money(p.total) }}</td>
                            <td class="p-3 text-center">
                                <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badgeEstado(p.estado)]">{{ p.estado }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
