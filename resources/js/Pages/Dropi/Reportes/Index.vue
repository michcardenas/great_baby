<script setup>
import { Head } from '@inertiajs/vue3';
import { BarChart3, TrendingUp, TrendingDown, AlertTriangle, Banknote } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    periodo: { type: Object, required: true },
    kpis: { type: Object, required: true },
    transportadoras: { type: Array, required: true },
    ciudades: { type: Array, required: true },
    vendedores: { type: Array, required: true },
});

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
</script>

<template>
    <Head title="Reportes Dropi"/>
    <AppLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <BarChart3 class="h-6 w-6 text-brand-600"/>
                Reportes Dropi
            </h1>
            <p class="text-sm text-surface-500">Periodo: {{ periodo.inicio }} → {{ periodo.fin }}</p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><TrendingUp class="h-3 w-3"/> Ventas mes</div>
                    <div class="text-2xl font-bold mt-1 text-emerald-600">{{ money(kpis.ventas) }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><TrendingDown class="h-3 w-3"/> Devoluciones</div>
                    <div class="text-2xl font-bold mt-1 text-red-600">{{ money(kpis.devoluciones) }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><Banknote class="h-3 w-3"/> Retiros banco</div>
                    <div class="text-2xl font-bold mt-1">{{ money(kpis.retiros) }}</div>
                </div>
                <div class="card p-4" :class="kpis.sanciones > 0 ? 'ring-2 ring-red-500' : ''">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><AlertTriangle class="h-3 w-3"/> Sanciones</div>
                    <div class="text-2xl font-bold mt-1" :class="kpis.sanciones > 0 ? 'text-red-600' : ''">{{ money(kpis.sanciones) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Transportadoras -->
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">🚚 Por transportadora</div>
                    <div v-if="!transportadoras.length" class="text-center py-4 text-surface-500 text-xs">Sin datos</div>
                    <table v-else class="w-full text-sm">
                        <thead class="text-[10px] text-surface-500 uppercase">
                            <tr>
                                <th class="text-left p-1">Nombre</th>
                                <th class="text-right p-1">Ped.</th>
                                <th class="text-right p-1">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in transportadoras" :key="t.nombre" class="border-t border-surface-100">
                                <td class="p-1 text-xs">{{ t.nombre }}</td>
                                <td class="p-1 text-right text-xs">{{ t.pedidos }}</td>
                                <td class="p-1 text-right font-bold text-xs">{{ money(t.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Ciudades -->
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">🏙 Top ciudades</div>
                    <div v-if="!ciudades.length" class="text-center py-4 text-surface-500 text-xs">Sin datos</div>
                    <table v-else class="w-full text-sm">
                        <thead class="text-[10px] text-surface-500 uppercase">
                            <tr>
                                <th class="text-left p-1">Ciudad</th>
                                <th class="text-right p-1">Ped.</th>
                                <th class="text-right p-1">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in ciudades" :key="c.nombre" class="border-t border-surface-100">
                                <td class="p-1 text-xs">{{ c.nombre }}</td>
                                <td class="p-1 text-right text-xs">{{ c.pedidos }}</td>
                                <td class="p-1 text-right font-bold text-xs">{{ money(c.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Vendedores -->
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">👤 Top vendedores</div>
                    <div v-if="!vendedores.length" class="text-center py-4 text-surface-500 text-xs">Sin datos</div>
                    <table v-else class="w-full text-sm">
                        <thead class="text-[10px] text-surface-500 uppercase">
                            <tr>
                                <th class="text-left p-1">Nombre</th>
                                <th class="text-right p-1">Ped.</th>
                                <th class="text-right p-1">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in vendedores" :key="v.nombre" class="border-t border-surface-100">
                                <td class="p-1 text-xs truncate max-w-[100px]" :title="v.nombre">{{ v.nombre }}</td>
                                <td class="p-1 text-right text-xs">{{ v.pedidos }}</td>
                                <td class="p-1 text-right font-bold text-xs">{{ money(v.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
