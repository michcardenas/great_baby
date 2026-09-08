<script setup>
import { Head, router } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { useIntervalFn } from '@vueuse/core';
import {
    AlertCircle, Package, Truck, CheckCircle, DollarSign, Undo2, MapPin, BarChart3,
} from 'lucide-vue-next';

import AppLayout from '@/Layouts/AppLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';
import LineChart from '@/Components/LineChart.vue';
import DoughnutChart from '@/Components/DoughnutChart.vue';
import ColombiaMap from '@/Components/ColombiaMap.vue';
import RankingTable from '@/Components/RankingTable.vue';
import AlertBanner from '@/Components/AlertBanner.vue';

const props = defineProps({
    kpis: { type: Object, required: true },
    serie7d: { type: Object, required: true },
    distribucion: { type: Object, required: true },
    mapa: { type: Array, required: true },
    ranking: { type: Array, required: true },
    comparativa: { type: Object, required: true },
    alertas: { type: Array, required: true },
    refreshSeg: { type: Number, default: 30 },
});

// Reloj vivo
const hora = ref(new Date().toLocaleTimeString('es-CO'));
const fecha = ref(new Date().toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }));
useIntervalFn(() => {
    hora.value = new Date().toLocaleTimeString('es-CO');
}, 1000);

// Estado de sincronización visible para el usuario.
const sincronizando = ref(false);
const ultimoSync = ref(new Date());
const errorSync = ref(false);

// Auto-refresh configurable + pausa cuando la tab está oculta (UX #19 auditor).
useIntervalFn(() => {
    if (document.visibilityState !== 'visible') return; // no refrescar si nadie mira
    sincronizando.value = true;
    errorSync.value = false;
    router.reload({
        only: ['kpis', 'serie7d', 'distribucion', 'mapa', 'ranking', 'comparativa', 'alertas'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { ultimoSync.value = new Date(); },
        onError: () => { errorSync.value = true; },
        onFinish: () => { sincronizando.value = false; },
    });
}, () => Math.max(10, props.refreshSeg) * 1000);

const fmtHoraSync = (d) => d ? d.toLocaleTimeString('es-CO') : '—';

const lineDatasets = () => [
    { label: 'Empacados', data: props.serie7d.empacados, color: '#10b981' },
    { label: 'Despachados', data: props.serie7d.despachados, color: '#3b82f6' },
];
</script>

<template>
    <Head title="Torre de Control"/>
    <AppLayout>
        <div class="space-y-4">
            <!-- Header con reloj -->
            <div class="card p-5 flex items-start justify-between bg-gradient-to-r from-surface-900/5 to-brand-500/5">
                <div>
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600">🗼 Torre de Control · Bodega</div>
                    <div class="text-sm text-surface-500 mt-1 capitalize">{{ fecha }}</div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-black text-brand-600 font-mono tabular-nums leading-none">{{ hora }}</div>
                    <div class="text-xs uppercase tracking-widest mt-1 flex items-center justify-end gap-1.5">
                        <span v-if="sincronizando" class="inline-block h-2 w-2 rounded-full bg-brand-500 animate-pulse" aria-hidden="true"></span>
                        <span v-else-if="errorSync" class="inline-block h-2 w-2 rounded-full bg-red-500" aria-hidden="true"></span>
                        <span v-else class="inline-block h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        <span v-if="sincronizando" class="text-brand-600">Sincronizando…</span>
                        <span v-else-if="errorSync" class="text-red-600">Sin conexión · reintentando</span>
                        <span v-else class="text-surface-500">Actualizado {{ fmtHoraSync(ultimoSync) }}</span>
                    </div>
                </div>
            </div>

            <!-- Alertas críticas -->
            <AlertBanner :alertas="alertas"/>

            <!-- KPIs (auto-fit para labels sin truncar) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
                <KpiCard label="Pendientes por empacar" :value="kpis.pendientes" color="red" :icon="AlertCircle" subtitle="requieren atención"/>
                <KpiCard label="Empacados hoy" :value="kpis.empacados_hoy" color="emerald" :icon="Package"
                         :subtitle="kpis.prom_seg > 0 ? '⏱ prom ' + Math.floor(kpis.prom_seg/60) + ':' + String(kpis.prom_seg%60).padStart(2,'0') : '—'"/>
                <KpiCard label="Despachados hoy" :value="kpis.despachados_hoy" color="blue" :icon="Truck" subtitle="salieron a ruta"/>
                <KpiCard label="Entregados hoy" :value="kpis.entregados_hoy" color="purple" :icon="CheckCircle" subtitle="confirmados"/>
                <KpiCard label="Ventas hoy" :value="kpis.ventas_hoy" color="amber" :icon="DollarSign" format="money"
                         :delta="comparativa.ventas.delta" delta-label="vs mes ant."/>
                <KpiCard label="Devoluciones hoy" :value="kpis.devoluciones_hoy" :color="kpis.tasa_devolucion > 10 ? 'red' : 'gray'" :icon="Undo2"
                         :subtitle="kpis.tasa_devolucion + '% del volumen'"/>
            </div>

            <!-- Fila 1: línea + donut -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="card p-4 lg:col-span-2">
                    <div class="flex items-center gap-2 mb-3">
                        <BarChart3 class="h-4 w-4 text-brand-600"/>
                        <h3 class="text-xs uppercase tracking-widest font-bold text-brand-600">Últimos 7 días · Flujo diario</h3>
                    </div>
                    <LineChart :labels="serie7d.labels" :datasets="lineDatasets()" height="240px"/>
                </div>
                <div class="card p-4">
                    <h3 class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">🎯 Distribución por estado</h3>
                    <DoughnutChart :labels="distribucion.labels" :values="distribucion.values" :colors="distribucion.colors" height="240px"/>
                </div>
            </div>

            <!-- Fila 2: mapa + ranking -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="card p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs uppercase tracking-widest font-bold text-brand-600 flex items-center gap-2">
                            <MapPin class="h-4 w-4"/>
                            Mapa de envíos · Colombia
                        </h3>
                        <span class="text-xs text-surface-500">{{ mapa.length }} ciudades</span>
                    </div>
                    <ColombiaMap :puntos="mapa" height="320px"/>
                </div>
                <div class="card p-4">
                    <h3 class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">🏆 Ranking operarios hoy</h3>
                    <RankingTable :filas="ranking"/>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
