<script setup>
import { Head } from '@inertiajs/vue3';
import { BarChart3, MapPin, Package } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    ubicaciones: { type: Array, required: true },
    topStock: { type: Array, required: true },
    kpis: { type: Object, required: true },
});
</script>

<template>
    <Head title="Reporte stock por bodega"/>
    <AppLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <BarChart3 class="h-6 w-6 text-brand-600"/>
                Reporte stock por bodega/ubicación
            </h1>

            <div class="grid grid-cols-3 gap-3">
                <div class="card p-4"><div class="text-xs text-surface-500 flex items-center gap-2"><MapPin class="h-3 w-3"/> Ubicaciones activas</div><div class="text-3xl font-bold mt-1">{{ kpis.ubicaciones_activas }}</div></div>
                <div class="card p-4"><div class="text-xs text-surface-500 flex items-center gap-2"><Package class="h-3 w-3"/> Unidades totales</div><div class="text-3xl font-bold mt-1 text-brand-600">{{ kpis.unidades_totales }}</div></div>
                <div class="card p-4"><div class="text-xs text-surface-500">SKUs con stock</div><div class="text-3xl font-bold mt-1">{{ kpis.skus_con_stock }}</div></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-3">Stock por ubicación</div>
                    <table class="w-full text-sm">
                        <thead class="text-[10px] uppercase text-surface-500 border-b">
                            <tr>
                                <th class="text-left p-2">Ubicación</th>
                                <th class="text-left p-2">Categoría</th>
                                <th class="text-right p-2">SKUs</th>
                                <th class="text-right p-2">Unidades</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="u in ubicaciones" :key="u.codigo">
                                <td class="p-2 font-mono">{{ u.codigo }}<div class="text-[10px] text-surface-500">{{ u.nombre }}</div></td>
                                <td class="p-2 text-xs">{{ u.categoria }}</td>
                                <td class="p-2 text-right">{{ u.skus }}</td>
                                <td class="p-2 text-right font-bold">{{ u.unidades }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-3">Top 30 con más stock</div>
                    <table class="w-full text-sm">
                        <thead class="text-[10px] uppercase text-surface-500 border-b">
                            <tr>
                                <th class="text-left p-2">SKU</th>
                                <th class="text-left p-2">Producto</th>
                                <th class="text-right p-2">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(t, i) in topStock" :key="i">
                                <td class="p-2 font-mono text-xs">{{ t.codigo }}</td>
                                <td class="p-2 text-xs">{{ t.producto }}</td>
                                <td class="p-2 text-right font-bold text-brand-600">{{ t.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
