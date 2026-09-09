<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { FileText, AlertCircle, DollarSign, Download } from 'lucide-vue-next';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { useMoney } from '@/composables/useMoney';

const props = defineProps({
    facturas: { type: Object, required: true },
    kpis: { type: Object, required: true },
});

const { money } = useMoney();

// Re-audit UX #15 · dark mode aplicado a badges.
const badgeEstado = (e) => ({
    borrador: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
    pendiente: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    pagada: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    abonada: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200',
    vencida: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    anulada: 'bg-surface-200 text-surface-600 dark:bg-surface-800 dark:text-surface-400',
}[e] || 'bg-surface-100 dark:bg-surface-800');
</script>

<template>
    <Head title="Mis facturas"/>
    <PortalLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <FileText class="h-6 w-6 text-brand-600"/>
                Mis facturas
            </h1>

            <div class="grid grid-cols-2 gap-3">
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 dark:text-surface-400 flex items-center gap-2"><DollarSign class="h-3 w-3"/> Saldo total</div>
                    <div class="text-2xl font-bold mt-1">{{ money(kpis.saldo_total) }}</div>
                </div>
                <div class="card p-4" :class="kpis.vencidas > 0 ? 'ring-2 ring-red-500' : ''">
                    <div class="text-xs uppercase text-surface-500 dark:text-surface-400 flex items-center gap-2"><AlertCircle class="h-3 w-3"/> Vencidas</div>
                    <div class="text-2xl font-bold mt-1" :class="kpis.vencidas > 0 ? 'text-red-600' : ''">{{ kpis.vencidas }}</div>
                </div>
            </div>

            <div v-if="!facturas.data.length" class="card p-12 text-center text-surface-500 dark:text-surface-400">Sin facturas registradas.</div>
            <div v-else class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 dark:text-surface-400 uppercase border-b border-surface-200 dark:border-surface-800">
                        <tr>
                            <th class="text-left p-3">Número</th>
                            <th class="text-left p-3">Emisión</th>
                            <th class="text-left p-3">Vence</th>
                            <th class="text-right p-3">Total</th>
                            <th class="text-right p-3">Saldo</th>
                            <th class="text-center p-3">Estado</th>
                            <th class="text-right p-3">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                        <tr v-for="f in facturas.data" :key="f.id" class="hover:bg-surface-50 dark:hover:bg-surface-800/50">
                            <td class="p-3 font-bold">
                                <!-- H2 · link a detalle en lugar de texto plano -->
                                <Link :href="`/portal/facturas/${f.id}`" class="text-brand-700 dark:text-brand-400 hover:underline">{{ f.numero }}</Link>
                            </td>
                            <td class="p-3">{{ f.emision }}</td>
                            <td class="p-3">{{ f.vence }}</td>
                            <td class="p-3 text-right">{{ money(f.total) }}</td>
                            <td class="p-3 text-right font-bold" :class="f.saldo > 0 ? 'text-red-600' : 'text-emerald-600'">{{ money(f.saldo) }}</td>
                            <td class="p-3 text-center">
                                <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badgeEstado(f.estado)]">{{ f.estado }}</span>
                            </td>
                            <td class="p-3 text-right">
                                <div class="inline-flex items-center gap-3 justify-end">
                                    <Link :href="`/portal/facturas/${f.id}`" class="text-xs text-surface-600 dark:text-surface-300 hover:text-brand-700 dark:hover:text-brand-400">Ver</Link>
                                    <!-- Re-audit UX #1 · botón PDF por fila -->
                                    <a :href="f.pdf_url" target="_blank" rel="noopener"
                                        class="text-xs text-brand-600 hover:underline font-semibold inline-flex items-center gap-1">
                                        <Download class="h-3 w-3"/> PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </PortalLayout>
</template>
