<script setup>
import { Head } from '@inertiajs/vue3';
import { BarChart3, MapPin, Package, DollarSign, Cloud, CloudOff } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useMoney } from '@/composables/useMoney';

const props = defineProps({
    ubicaciones: { type: Array, required: true },
    topStock: { type: Array, required: true },
    kpis: { type: Object, required: true },
});

const { money } = useMoney();
</script>

<template>
    <Head title="Reporte stock por bodega · SIIGO"/>
    <AppLayout>
        <div class="space-y-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <BarChart3 class="h-6 w-6 text-brand-600"/>
                    Reporte stock por bodega · Formato SIIGO
                </h1>
                <p class="text-sm text-surface-500 mt-1">
                    Valorización de inventario a costo promedio ponderado (base contable SIIGO).
                </p>
            </div>

            <!-- KPIs incluyen valorización -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card p-4">
                    <div class="text-xs text-surface-500 flex items-center gap-2"><MapPin class="h-3 w-3"/> Bodegas</div>
                    <div class="text-3xl font-bold mt-1">{{ kpis.ubicaciones_activas }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs text-surface-500 flex items-center gap-2"><Package class="h-3 w-3"/> Unidades</div>
                    <div class="text-3xl font-bold mt-1 text-brand-600">{{ kpis.unidades_totales.toLocaleString('es-CO') }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs text-surface-500">SKUs con stock</div>
                    <div class="text-3xl font-bold mt-1">{{ kpis.skus_con_stock }}</div>
                </div>
                <div class="card p-4 bg-brand-50 dark:bg-brand-950/30 border-brand-200">
                    <div class="text-xs text-surface-500 flex items-center gap-2"><DollarSign class="h-3 w-3"/> Valor total inventario</div>
                    <div class="text-2xl font-bold mt-1 text-brand-700">{{ money(kpis.valor_total_inventario) }}</div>
                    <div class="text-[10px] text-surface-500 mt-1">a costo promedio · base SIIGO</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Stock por ubicación con valorización -->
                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-3">Stock por ubicación (valorizado)</div>
                    <table class="w-full text-sm">
                        <thead class="text-[10px] uppercase text-surface-500 border-b">
                            <tr>
                                <th class="text-left p-2">Ubicación</th>
                                <th class="text-left p-2">Categoría</th>
                                <th class="text-right p-2">SKUs</th>
                                <th class="text-right p-2">Unidades</th>
                                <th class="text-right p-2">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="u in ubicaciones" :key="u.codigo" class="hover:bg-surface-50">
                                <td class="p-2 font-mono">{{ u.codigo }}<div class="text-[10px] text-surface-500">{{ u.nombre }}</div></td>
                                <td class="p-2 text-xs">{{ u.categoria }}</td>
                                <td class="p-2 text-right">{{ u.skus }}</td>
                                <td class="p-2 text-right font-bold">{{ u.unidades.toLocaleString('es-CO') }}</td>
                                <td class="p-2 text-right font-bold font-mono text-brand-700">{{ money(u.valor) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="ubicaciones.length" class="border-t-2">
                            <tr class="font-bold bg-brand-50/50 dark:bg-brand-950/20">
                                <td class="p-2 text-xs uppercase">Total</td>
                                <td class="p-2"></td>
                                <td class="p-2"></td>
                                <td class="p-2 text-right">{{ kpis.unidades_totales.toLocaleString('es-CO') }}</td>
                                <td class="p-2 text-right font-mono text-brand-800">{{ money(kpis.valor_total_inventario) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Top con costo promedio y SIIGO -->
                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-3">Top 30 con más stock</div>
                    <table class="w-full text-sm">
                        <thead class="text-[10px] uppercase text-surface-500 border-b">
                            <tr>
                                <th class="text-left p-2">SKU</th>
                                <th class="text-left p-2">Producto</th>
                                <th class="text-right p-2">Unid.</th>
                                <th class="text-right p-2">Costo prom.</th>
                                <th class="text-right p-2">Valor</th>
                                <th class="text-center p-2">SIIGO</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(t, i) in topStock" :key="i" class="hover:bg-surface-50">
                                <td class="p-2 font-mono text-xs">{{ t.codigo }}</td>
                                <td class="p-2 text-xs">{{ t.producto }}</td>
                                <td class="p-2 text-right font-bold text-brand-600">{{ t.total.toLocaleString('es-CO') }}</td>
                                <td class="p-2 text-right font-mono text-xs">{{ t.costo_prom > 0 ? money(t.costo_prom) : '—' }}</td>
                                <td class="p-2 text-right font-mono font-bold text-brand-700">{{ t.valor > 0 ? money(t.valor) : '—' }}</td>
                                <td class="p-2 text-center">
                                    <Cloud v-if="t.siigo_code" class="h-3 w-3 text-emerald-600 inline" :title="`SIIGO: ${t.siigo_code}`"/>
                                    <CloudOff v-else class="h-3 w-3 text-amber-600 inline" title="Pendiente sync SIIGO"/>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Nota SIIGO -->
            <div class="card p-3 text-xs text-surface-500">
                <p>
                    <strong>Costo promedio ponderado</strong> = Σ(cantidad × costo_unit de entradas) / Σ(cantidad entradas).
                    <strong>Valor</strong> = saldo actual × costo promedio.
                    Este es el valor que reporta SIIGO al cierre de inventario. Diferencias entre este total y el
                    saldo contable SIIGO en cuenta 1435 indican asientos no sincronizados
                    (ver <a href="/app/siigo" class="text-brand-600 underline">panel de sincronización</a>).
                </p>
            </div>
        </div>
    </AppLayout>
</template>
