<script setup>
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, DollarSign, HelpCircle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    pedidosSinCobro: { type: Array, required: true },
    movimientosHuerfanos: { type: Array, required: true },
    kpis: { type: Object, required: true },
});

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
</script>

<template>
    <Head title="Discrepancias wallet"/>
    <AppLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <AlertTriangle class="h-6 w-6 text-brand-600"/>
                Discrepancias wallet Dropi
            </h1>
            <p class="text-sm text-surface-500">Cruce entre pedidos pagados y movimientos de wallet — detecta dinero no cobrado o movimientos sin pedido.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="card p-4" :class="kpis.pedidos_sin_cobro > 0 ? 'ring-2 ring-red-500' : ''">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><AlertTriangle class="h-3 w-3"/> Pedidos sin cobro</div>
                    <div class="text-3xl font-bold mt-1" :class="kpis.pedidos_sin_cobro > 0 ? 'text-red-600' : ''">{{ kpis.pedidos_sin_cobro }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><DollarSign class="h-3 w-3"/> Monto faltante</div>
                    <div class="text-2xl font-bold mt-1 text-red-600">{{ money(kpis.monto_faltante) }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><HelpCircle class="h-3 w-3"/> Movimientos huérfanos</div>
                    <div class="text-3xl font-bold mt-1">{{ kpis.movimientos_huerfanos }}</div>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-red-600 mb-3">Pedidos pagados SIN movimiento en wallet</div>
                <div v-if="!pedidosSinCobro.length" class="text-center py-6 text-emerald-600 text-sm">✅ Todo cuadra — cada pedido pagado tiene su cobro registrado.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-surface-500 uppercase border-b">
                            <tr>
                                <th class="text-left p-2">Guía</th>
                                <th class="text-left p-2">Dropi ID</th>
                                <th class="text-left p-2">Cliente</th>
                                <th class="text-left p-2">Pagado</th>
                                <th class="text-right p-2">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100">
                            <tr v-for="p in pedidosSinCobro" :key="p.id" class="hover:bg-surface-50">
                                <td class="p-2 font-mono text-xs">{{ p.guia }}</td>
                                <td class="p-2 font-mono text-xs">{{ p.dropi_id }}</td>
                                <td class="p-2">{{ p.cliente }}</td>
                                <td class="p-2 text-xs">{{ p.pagado }}</td>
                                <td class="p-2 text-right font-bold text-red-600">{{ money(p.monto) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-amber-600 mb-3">Movimientos wallet SIN pedido asociado</div>
                <div v-if="!movimientosHuerfanos.length" class="text-center py-6 text-surface-500 text-sm">Sin movimientos huérfanos.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-surface-500 uppercase border-b">
                            <tr>
                                <th class="text-left p-2">Fecha</th>
                                <th class="text-left p-2">Tipo</th>
                                <th class="text-left p-2">Categoría</th>
                                <th class="text-left p-2">Ref Dropi</th>
                                <th class="text-right p-2">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100">
                            <tr v-for="m in movimientosHuerfanos" :key="m.id" class="hover:bg-surface-50">
                                <td class="p-2 text-xs">{{ m.fecha }}</td>
                                <td class="p-2 text-xs">{{ m.tipo }}</td>
                                <td class="p-2 text-xs">{{ m.categoria }}</td>
                                <td class="p-2 font-mono text-xs">{{ m.referencia }}</td>
                                <td class="p-2 text-right font-bold">{{ money(m.monto) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
