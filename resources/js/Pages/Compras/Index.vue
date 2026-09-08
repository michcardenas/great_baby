<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingCart, Ship, Package, DollarSign } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    tab: { type: String, default: 'ordenes' },
    kpis: { type: Object, required: true },
    ordenes: { type: Array, required: true },
    importaciones: { type: Array, required: true },
    recepciones: { type: Array, required: true },
});
const tabAct = ref(props.tab);
const fmtCOP = (n) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO');

const badge = (color) => ({
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    danger: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    gray: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
}[color] || 'bg-slate-100');
</script>

<template>
    <Head title="Compras e importaciones"/>
    <AppLayout>
        <div class="space-y-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <ShoppingCart class="h-6 w-6 text-brand-600"/>
                    Compras e importaciones
                </h1>
                <p class="text-sm text-surface-500 mt-1">Órdenes de compra, contenedores, recepciones a bodega.</p>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <KpiCard label="OC abiertas" :value="kpis.oc_abiertas" color="amber" :icon="ShoppingCart"/>
                <KpiCard label="Valor por recibir" :value="kpis.oc_valor_pendiente" color="blue" format="money" :icon="DollarSign"/>
                <KpiCard label="Contenedores en ruta" :value="kpis.contenedores_en_ruta" color="purple" :icon="Ship"/>
                <KpiCard label="Recepciones del mes" :value="kpis.recepciones_mes" color="emerald" :icon="Package"/>
            </div>

            <!-- Tabs -->
            <div class="flex items-center gap-2 border-b border-surface-200 dark:border-surface-800">
                <button @click="tabAct='ordenes'" :class="['px-4 py-2 text-sm font-semibold border-b-2', tabAct==='ordenes' ? 'border-brand-500 text-brand-600' : 'border-transparent text-surface-500']">
                    Órdenes ({{ ordenes.length }})
                </button>
                <button @click="tabAct='importaciones'" :class="['px-4 py-2 text-sm font-semibold border-b-2', tabAct==='importaciones' ? 'border-brand-500 text-brand-600' : 'border-transparent text-surface-500']">
                    Importaciones ({{ importaciones.length }})
                </button>
                <button @click="tabAct='recepciones'" :class="['px-4 py-2 text-sm font-semibold border-b-2', tabAct==='recepciones' ? 'border-brand-500 text-brand-600' : 'border-transparent text-surface-500']">
                    Recepciones ({{ recepciones.length }})
                </button>
            </div>

            <!-- Órdenes -->
            <div v-if="tabAct==='ordenes'" class="card overflow-hidden">
                <div v-if="!ordenes.length" class="text-center py-12 text-surface-500 text-sm">Sin órdenes de compra registradas.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[800px] text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900"><tr class="text-surface-500 text-xs uppercase">
                            <th class="text-left px-4 py-2">Número</th>
                            <th class="text-left">Proveedor</th>
                            <th class="text-left">Bodega</th>
                            <th class="text-right">Emisión</th>
                            <th class="text-right">Esperada</th>
                            <th class="text-right">Total</th>
                            <th class="text-center">Estado</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="o in ordenes" :key="o.id" class="border-t border-surface-100 dark:border-surface-900">
                                <td class="px-4 py-2 font-mono font-semibold">
                                    <Link :href="`/app/compras/oc/${o.id}`" class="text-brand-600 hover:underline">{{ o.numero }}</Link>
                                </td>
                                <td>{{ o.proveedor || '—' }}</td>
                                <td class="text-xs text-surface-500">{{ o.bodega || '—' }}</td>
                                <td class="text-right text-surface-500 text-xs">{{ o.fecha_emision }}</td>
                                <td class="text-right text-surface-500 text-xs">{{ o.fecha_esperada || '—' }}</td>
                                <td class="text-right font-bold font-mono">{{ fmtCOP(o.total) }}</td>
                                <td class="text-center"><span :class="['px-2 py-0.5 rounded text-xs font-bold', badge(o.estado_color)]">{{ o.estado_label }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Importaciones -->
            <div v-if="tabAct==='importaciones'" class="card overflow-hidden">
                <div v-if="!importaciones.length" class="text-center py-12 text-surface-500 text-sm">Sin importaciones registradas.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900"><tr class="text-surface-500 text-xs uppercase">
                            <th class="text-left px-4 py-2">Número</th>
                            <th class="text-left">Contenedor</th>
                            <th class="text-left">Origen</th>
                            <th class="text-right">Zarpe</th>
                            <th class="text-right">ETA</th>
                            <th class="text-right">Llegada</th>
                            <th class="text-right">Costo total</th>
                            <th class="text-center">Estado</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="i in importaciones" :key="i.id" class="border-t border-surface-100 dark:border-surface-900">
                                <td class="px-4 py-2 font-mono font-semibold">
                                    <Link :href="`/app/compras/importacion/${i.id}`" class="text-brand-600 hover:underline">{{ i.numero }}</Link>
                                </td>
                                <td class="text-xs font-mono">{{ i.contenedor || '—' }}</td>
                                <td class="text-xs">{{ i.proveedor_pais || '—' }} → {{ i.puerto_destino || '—' }}</td>
                                <td class="text-right text-xs text-surface-500">{{ i.zarpe }}</td>
                                <td class="text-right text-xs text-surface-500">{{ i.eta }}</td>
                                <td class="text-right text-xs text-surface-500">{{ i.llegada || '—' }}</td>
                                <td class="text-right font-bold font-mono">{{ i.valor_total_costo > 0 ? fmtCOP(i.valor_total_costo) : '—' }}</td>
                                <td class="text-center"><span :class="['px-2 py-0.5 rounded text-xs font-bold', badge(i.estado_color)]">{{ i.estado_label }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recepciones -->
            <div v-if="tabAct==='recepciones'" class="card overflow-hidden">
                <div v-if="!recepciones.length" class="text-center py-12 text-surface-500 text-sm">Sin recepciones registradas.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900"><tr class="text-surface-500 text-xs uppercase">
                            <th class="text-left px-4 py-2">Fecha</th>
                            <th class="text-left">OC</th>
                            <th class="text-left">Estado</th>
                            <th class="text-left">Recibió</th>
                            <th class="text-left">Observaciones</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="r in recepciones" :key="r.id" class="border-t border-surface-100 dark:border-surface-900">
                                <td class="px-4 py-2 text-surface-500">{{ r.fecha_recepcion }}</td>
                                <td class="font-mono">
                                    <Link :href="`/app/compras/recepcion/${r.id}`" class="text-brand-600 hover:underline">{{ r.orden_numero || 'R#'+r.id }}</Link>
                                </td>
                                <td class="capitalize">{{ r.estado }}</td>
                                <td class="text-xs">{{ r.receptor }}</td>
                                <td class="text-xs text-surface-500">{{ r.observaciones || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
