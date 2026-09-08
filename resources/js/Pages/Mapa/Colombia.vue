<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Map, TrendingUp } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    departamentos: Array,
    ciudades: Array,
    total_ventas: Number,
    total_pedidos: Number,
    periodo: Object,
});

const desde = ref(props.periodo.desde);
const hasta = ref(props.periodo.hasta);
const filtrar = () => router.get('/app/mapa-colombia', { desde: desde.value, hasta: hasta.value });

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });

const max = computed(() => Math.max(...props.departamentos.map(d => d.total), 1));

// Heatmap: color según % del máximo
const colorHeat = (pct) => {
    if (pct >= 0.75) return 'bg-red-600 text-white';
    if (pct >= 0.5) return 'bg-orange-500 text-white';
    if (pct >= 0.25) return 'bg-amber-400';
    if (pct > 0) return 'bg-amber-200';
    return 'bg-surface-100 text-surface-400';
};

// Grid Colombia: 32 departamentos + Bogotá DC ordenados
const deptosColombia = [
    'AMAZONAS','ANTIOQUIA','ARAUCA','ATLÁNTICO','BOLÍVAR','BOYACÁ','CALDAS','CAQUETÁ',
    'CASANARE','CAUCA','CESAR','CHOCÓ','CÓRDOBA','CUNDINAMARCA','GUAINÍA','GUAVIARE',
    'HUILA','LA GUAJIRA','MAGDALENA','META','NARIÑO','NORTE DE SANTANDER','PUTUMAYO',
    'QUINDÍO','RISARALDA','SAN ANDRÉS Y PROVIDENCIA','SANTANDER','SUCRE','TOLIMA',
    'VALLE DEL CAUCA','VAUPÉS','VICHADA','BOGOTÁ D.C.',
];

const buscarDepto = (n) => {
    const nu = n.toUpperCase();
    return props.departamentos.find(d => d.nombre.toUpperCase().includes(nu) || nu.includes(d.nombre.toUpperCase())) ||
        { total: 0, pedidos: 0 };
};
</script>

<template>
    <Head title="Mapa de Colombia"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Map class="h-6 w-6 text-brand-600"/>
                        Mapa de calor Colombia · Ventas Dropi
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">
                        {{ money(total_ventas) }} en {{ total_pedidos }} pedidos · {{ periodo.desde }} → {{ periodo.hasta }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="date" v-model="desde" class="input"/>
                    <input type="date" v-model="hasta" class="input"/>
                    <button @click="filtrar" class="btn-primary">Aplicar</button>
                </div>
            </div>

            <!-- Leyenda -->
            <div class="card p-3 flex items-center gap-4 text-xs">
                <span class="font-bold uppercase text-surface-500">Intensidad:</span>
                <div class="flex items-center gap-1"><div class="w-4 h-4 rounded bg-surface-100"></div>Sin ventas</div>
                <div class="flex items-center gap-1"><div class="w-4 h-4 rounded bg-amber-200"></div>Baja</div>
                <div class="flex items-center gap-1"><div class="w-4 h-4 rounded bg-amber-400"></div>Media</div>
                <div class="flex items-center gap-1"><div class="w-4 h-4 rounded bg-orange-500"></div>Alta</div>
                <div class="flex items-center gap-1"><div class="w-4 h-4 rounded bg-red-600"></div>Top</div>
            </div>

            <!-- Grid heatmap de departamentos -->
            <div class="card p-4">
                <div class="text-xs uppercase font-bold text-brand-600 mb-3">33 departamentos + DC · Heatmap</div>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">
                    <div v-for="d in deptosColombia" :key="d"
                        :class="['rounded-lg p-3 text-center transition-transform hover:scale-105 cursor-default', colorHeat(buscarDepto(d).total / max)]">
                        <div class="text-[10px] uppercase font-bold truncate">{{ d }}</div>
                        <div class="text-sm font-bold mt-1">{{ money(buscarDepto(d).total) }}</div>
                        <div class="text-[10px] opacity-75">{{ buscarDepto(d).pedidos }} pedidos</div>
                    </div>
                </div>
            </div>

            <!-- Top 30 ciudades -->
            <div class="card p-4">
                <div class="text-xs uppercase font-bold text-brand-600 mb-3 flex items-center gap-2">
                    <TrendingUp class="h-3 w-3"/> Top 30 ciudades
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                    <div v-for="(c, i) in ciudades" :key="i" class="flex items-center justify-between p-2 rounded bg-surface-50 dark:bg-surface-800">
                        <div class="min-w-0">
                            <div class="font-medium text-sm truncate">{{ c.nombre }}</div>
                            <div class="text-[10px] text-surface-500">{{ c.depto }} · {{ c.pedidos }} pedidos</div>
                        </div>
                        <div class="text-sm font-bold text-brand-600">{{ money(c.total) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
