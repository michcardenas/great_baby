<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { FileText, Search, Filter, Phone, ExternalLink, Download } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    facturas: { type: Object, required: true },
    filtros: { type: Object, required: true },
    totales: { type: Object, required: true },
});

const q = ref(props.filtros.q || '');
const filtro = ref(props.filtros.filtro || 'todas');

const buscar = useDebounceFn(() => {
    router.get('/app/facturas', { q: q.value, filtro: filtro.value }, {
        preserveScroll: true, preserveState: true, replace: true,
    });
}, 400);

watch(q, buscar);
watch(filtro, () => {
    router.get('/app/facturas', { q: q.value, filtro: filtro.value }, {
        preserveScroll: true, preserveState: true, replace: true,
    });
});

const fmtCOP = (n) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO');

const tabs = computed(() => [
    { key: 'todas', label: 'Todas', count: props.totales.todas, color: 'slate' },
    { key: 'pendientes', label: 'Pendientes', count: props.totales.pendientes, color: 'amber' },
    { key: 'vencidas', label: 'Vencidas', count: props.totales.vencidas, color: 'red' },
    { key: 'pagadas', label: 'Pagadas', count: props.totales.pagadas, color: 'emerald' },
]);

const badgeEstado = (color) => ({
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    danger: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    gray: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
}[color] || 'bg-slate-100 text-slate-700');

const abrirWhatsApp = (tel, nombre, numero, saldo) => {
    if (! tel) return;
    const t = String(tel).replace(/\D/g, '');
    if (t.length < 10) return;
    const num = t.startsWith('57') ? t : '57' + t;
    const msg = encodeURIComponent(`Hola ${nombre}, recordate del pago de la factura ${numero} por ${fmtCOP(saldo)}. Gracias.`);
    window.open(`https://wa.me/${num}?text=${msg}`, '_blank');
};
</script>

<template>
    <Head title="Facturas de venta"/>
    <AppLayout>
        <div class="space-y-4">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <FileText class="h-6 w-6 text-brand-600"/>
                        Facturas de venta
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">Consultá el estado, gestioná cobros y descargá PDF/QR DIAN.</p>
                </div>
                <Link href="/app/cartera" class="btn-secondary text-sm">
                    ← Dashboard cartera
                </Link>
            </div>

            <!-- Tabs -->
            <div class="flex items-center gap-2 border-b border-surface-200 dark:border-surface-800 overflow-x-auto">
                <button v-for="t in tabs" :key="t.key" @click="filtro = t.key"
                        :class="['px-4 py-2 text-sm font-semibold border-b-2 whitespace-nowrap transition',
                                 filtro === t.key
                                 ? 'border-brand-500 text-brand-600'
                                 : 'border-transparent text-surface-500 hover:text-surface-800 dark:hover:text-surface-200']">
                    {{ t.label }}
                    <span class="ml-1 text-xs opacity-70">({{ t.count }})</span>
                </button>
            </div>

            <!-- Buscador -->
            <div class="relative">
                <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-surface-400"/>
                <input v-model="q" type="search"
                       placeholder="Buscar por número, cliente o documento…"
                       class="input w-full pl-10"/>
            </div>

            <!-- Tabla -->
            <div class="card overflow-hidden">
                <div v-if="! facturas.data.length" class="text-center py-16 text-surface-500">
                    <FileText class="h-10 w-10 mx-auto opacity-40"/>
                    <div class="text-sm mt-2">No hay facturas con estos criterios.</div>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900">
                            <tr class="text-surface-500 text-xs uppercase">
                                <th class="text-left px-4 py-2">Número</th>
                                <th class="text-left">Cliente</th>
                                <th class="text-right">Emisión</th>
                                <th class="text-right">Vence</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Saldo</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Mora</th>
                                <th class="text-right px-2">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="f in facturas.data" :key="f.id"
                                class="border-t border-surface-100 dark:border-surface-900 hover:bg-surface-50 dark:hover:bg-surface-900/30">
                                <td class="px-4 py-2 font-mono text-brand-600 font-semibold">
                                    <Link :href="'/app/facturas/' + f.id" class="hover:underline">{{ f.numero }}</Link>
                                </td>
                                <td>
                                    <div class="font-medium text-surface-800 dark:text-surface-200">{{ f.contacto }}</div>
                                    <div v-if="f.telefono" class="text-xs text-surface-500 font-mono">{{ f.telefono }}</div>
                                </td>
                                <td class="text-right text-surface-500">{{ f.fecha_emision }}</td>
                                <td class="text-right text-surface-500">{{ f.fecha_vencimiento }}</td>
                                <td class="text-right font-bold tabular-nums">{{ fmtCOP(f.total) }}</td>
                                <td class="text-right font-bold tabular-nums" :class="f.saldo > 0 ? 'text-red-600' : 'text-emerald-600'">
                                    {{ fmtCOP(f.saldo) }}
                                </td>
                                <td class="text-center">
                                    <span :class="['inline-block px-2 py-0.5 rounded text-xs font-bold', badgeEstado(f.estado_color)]">
                                        {{ f.estado_label }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span v-if="f.dias_mora > 0" class="text-xs font-bold text-red-600">{{ f.dias_mora }}d</span>
                                    <span v-else class="text-xs text-emerald-600">—</span>
                                </td>
                                <td class="text-right px-2">
                                    <div class="inline-flex items-center gap-1">
                                        <a :href="'/cartera/factura/' + f.id + '/pdf'" target="_blank"
                                           class="text-brand-600 hover:text-brand-800 p-1" title="Descargar PDF" aria-label="Descargar PDF">
                                            <Download class="h-4 w-4"/>
                                        </a>
                                        <button v-if="f.telefono && f.saldo > 0"
                                                @click="abrirWhatsApp(f.telefono, f.contacto, f.numero, f.saldo)"
                                                class="text-emerald-600 hover:text-emerald-800 p-1" title="Cobrar por WhatsApp" aria-label="Cobrar por WhatsApp">
                                            <Phone class="h-4 w-4"/>
                                        </button>
                                        <Link :href="'/app/facturas/' + f.id"
                                              class="text-surface-500 hover:text-brand-600 p-1" title="Ver detalle" aria-label="Ver detalle">
                                            <ExternalLink class="h-4 w-4"/>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="facturas.data.length" class="flex items-center justify-between px-4 py-3 border-t border-surface-200 dark:border-surface-800 text-sm">
                    <div class="text-surface-500">{{ facturas.from }}–{{ facturas.to }} de {{ facturas.total }}</div>
                    <div class="flex items-center gap-1">
                        <template v-for="link in facturas.links" :key="link.label">
                            <Link v-if="link.url" :href="link.url"
                                  :class="['px-2 py-1 rounded text-xs',
                                          link.active ? 'bg-brand-600 text-white' : 'text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800']"
                                  v-html="link.label"/>
                            <span v-else class="px-2 py-1 rounded text-xs text-surface-400" v-html="link.label"/>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
