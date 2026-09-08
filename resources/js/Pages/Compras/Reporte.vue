<script setup>
import { Head } from '@inertiajs/vue3';
import { BarChart3, Package, DollarSign, AlertCircle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    periodo: { type: Object, required: true },
    kpis: { type: Object, required: true },
    topProveedores: { type: Array, required: true },
});

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
</script>

<template>
    <Head title="Reporte compras"/>
    <AppLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <BarChart3 class="h-6 w-6 text-brand-600"/>
                Reporte de compras
            </h1>
            <p class="text-sm text-surface-500">Periodo: {{ periodo.inicio }} → {{ periodo.fin }}</p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card p-4" :class="kpis.oc_pendientes > 0 ? 'ring-2 ring-blue-500' : ''">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><Package class="h-3 w-3"/> OCs pendientes</div>
                    <div class="text-3xl font-bold mt-1">{{ kpis.oc_pendientes }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><DollarSign class="h-3 w-3"/> Compras mes</div>
                    <div class="text-2xl font-bold mt-1 text-brand-600">{{ money(kpis.oc_este_mes) }}</div>
                </div>
                <div class="card p-4" :class="kpis.contenedores_por_liquidar > 0 ? 'ring-2 ring-amber-500' : ''">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><AlertCircle class="h-3 w-3"/> Por liquidar</div>
                    <div class="text-3xl font-bold mt-1 text-amber-600">{{ kpis.contenedores_por_liquidar }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500">FOB mes</div>
                    <div class="text-2xl font-bold mt-1">{{ money(kpis.total_fob_mes) }}</div>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase font-bold text-brand-600 mb-3">Top proveedores del mes</div>
                <div v-if="!topProveedores.length" class="text-center py-6 text-surface-500 text-sm">Sin compras en el mes.</div>
                <table v-else class="w-full text-sm">
                    <thead class="text-[10px] text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-2">Proveedor</th>
                            <th class="text-right p-2">OCs</th>
                            <th class="text-right p-2">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="p in topProveedores" :key="p.nombre" class="hover:bg-surface-50">
                            <td class="p-2 font-medium">{{ p.nombre }}</td>
                            <td class="p-2 text-right">{{ p.ocs }}</td>
                            <td class="p-2 text-right font-bold text-brand-600">{{ money(p.total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
