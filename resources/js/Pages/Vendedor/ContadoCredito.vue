<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { DollarSign, CreditCard, Hourglass, PieChart, BarChart3 } from 'lucide-vue-next';

const props = defineProps({
    periodo: String,
    rango: Object,
    contado: Object,
    credito: Object,
    es_super: Boolean,
});

const money = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0);
const cambiarPeriodo = (p) => router.get('/app/vendedor/contado-credito', { periodo: p }, { preserveState: true });

const periodos = [
    { key: 'mes-actual', label: 'Mes actual' },
    { key: 'mes-anterior', label: 'Mes anterior' },
    { key: 'trimestre-actual', label: 'Trimestre' },
    { key: 'anio-actual', label: 'Año' },
];

// En GB la "Pendiente" es el saldo del crédito aún no cobrado.
const pendiente = computed(() => ({
    cantidad: props.credito.saldo_pendiente > 0 ? props.credito.cantidad : 0,
    monto: props.credito.saldo_pendiente,
}));

const totalMonto = computed(() => props.contado.monto + props.credito.monto);
const part = (m) => totalMonto.value > 0 ? Math.round((m / totalMonto.value) * 100) : 0;

const cats = computed(() => [
    { key: 'contado', label: 'Contado (pagado)', color: '#F7A8DC', data: props.contado, part: part(props.contado.monto) },
    { key: 'pendiente', label: 'Pendiente de cobro', color: '#F6C177', data: pendiente.value, part: part(pendiente.value.monto) },
    { key: 'credito', label: 'Crédito', color: '#C9B8F5', data: props.credito, part: part(props.credito.monto) },
]);

// Dona SVG: cada segmento es un arco con stroke-dasharray.
const donaSegs = computed(() => {
    const r = 60, c = 2 * Math.PI * r;
    let off = 0;
    return cats.value.filter(x => x.data.monto > 0).map(x => {
        const len = (x.data.monto / totalMonto.value) * c;
        const seg = { color: x.color, dash: `${len} ${c - len}`, off: -off, label: x.part + '%', pLabel: x.part };
        off += len;
        return seg;
    });
});
</script>

<template>
    <Head title="Mis Ventas por Tipo de Pago"/>
    <AppLayout>
        <div class="space-y-5 max-w-6xl mx-auto">
            <!-- Encabezado + presets -->
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Mis ventas por tipo de pago</h1>
                    <p class="text-sm text-surface-500 mt-1">{{ rango.inicio }} → {{ rango.fin }}</p>
                </div>
                <div class="flex gap-1 flex-wrap">
                    <button v-for="p in periodos" :key="p.key" @click="cambiarPeriodo(p.key)"
                            :class="['px-3 py-1.5 text-xs rounded-lg',
                                     periodo === p.key ? 'bg-brand-600 text-white' : 'bg-surface-100 dark:bg-surface-800 hover:bg-surface-200']">
                        {{ p.label }}
                    </button>
                </div>
            </div>

            <!-- 3 cards principales -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card p-4 border-l-4" style="border-left-color:#F7A8DC">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs uppercase font-semibold" style="color:#C13584">Contado (pagado)</div>
                        <DollarSign class="h-4 w-4" style="color:#F7A8DC"/>
                    </div>
                    <div class="text-2xl font-black">{{ money(contado.monto) }}</div>
                    <div class="text-xs text-surface-500 mt-1">
                        {{ contado.cantidad }} ventas · {{ part(contado.monto) }}%
                    </div>
                </div>
                <div class="card p-4 border-l-4" style="border-left-color:#F6C177">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs uppercase font-semibold" style="color:#B8860B">Pendiente de pago</div>
                        <Hourglass class="h-4 w-4" style="color:#F6C177"/>
                    </div>
                    <div class="text-2xl font-black">{{ money(pendiente.monto) }}</div>
                    <div class="text-xs text-surface-500 mt-1">
                        {{ pendiente.cantidad }} ventas · {{ part(pendiente.monto) }}%
                    </div>
                </div>
                <div class="card p-4 border-l-4" style="border-left-color:#C9B8F5">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs uppercase font-semibold" style="color:#6B46C1">Crédito</div>
                        <CreditCard class="h-4 w-4" style="color:#C9B8F5"/>
                    </div>
                    <div class="text-2xl font-black">{{ money(credito.monto) }}</div>
                    <div class="text-xs text-surface-500 mt-1">
                        {{ credito.cantidad }} ventas · {{ part(credito.monto) }}%
                    </div>
                </div>
            </div>

            <!-- Dona + Participación -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                <div class="card p-4 lg:col-span-2">
                    <div class="flex items-center gap-2 mb-3">
                        <PieChart class="h-5 w-5 text-brand-600"/>
                        <h3 class="font-semibold">Distribución</h3>
                    </div>
                    <div v-if="totalMonto === 0" class="text-center text-surface-400 py-10">
                        <PieChart class="h-8 w-8 mx-auto mb-2 opacity-50"/>
                        Sin ventas en este período
                    </div>
                    <div v-else class="flex items-center justify-center">
                        <svg viewBox="0 0 160 160" class="w-56 h-56">
                            <circle cx="80" cy="80" r="60" fill="none" stroke="#f3eefb" stroke-width="22"/>
                            <circle v-for="(s, i) in donaSegs" :key="i"
                                    cx="80" cy="80" r="60"
                                    fill="none" :stroke="s.color" stroke-width="22"
                                    :stroke-dasharray="s.dash" :stroke-dashoffset="s.off"
                                    transform="rotate(-90 80 80)"
                                    class="transition-all"/>
                            <text x="80" y="78" text-anchor="middle" class="text-xs fill-current text-surface-500">Total</text>
                            <text x="80" y="94" text-anchor="middle" class="text-sm font-bold fill-current">
                                {{ money(totalMonto).replace(',00', '') }}
                            </text>
                        </svg>
                    </div>
                </div>

                <div class="card p-4 lg:col-span-3">
                    <div class="flex items-center gap-2 mb-4">
                        <BarChart3 class="h-5 w-5 text-brand-600"/>
                        <h3 class="font-semibold">Participación por tipo de pago</h3>
                    </div>
                    <div class="space-y-4">
                        <div v-for="c in cats" :key="c.key">
                            <div class="flex justify-between mb-1 text-sm">
                                <span class="font-semibold flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full" :style="{ background: c.color }"></span>
                                    {{ c.label }}
                                </span>
                                <span>{{ money(c.data.monto) }} · {{ c.part }}%</span>
                            </div>
                            <div class="h-3 rounded-full overflow-hidden" style="background:#f3eefb">
                                <div class="h-full transition-all" :style="{ width: c.part + '%', background: c.color }"></div>
                            </div>
                            <div class="text-xs text-surface-500 mt-1">{{ c.data.cantidad }} ventas</div>
                        </div>
                        <hr class="border-surface-200 dark:border-surface-800">
                        <div class="flex justify-between font-bold">
                            <span>Total ventas</span>
                            <span>{{ money(totalMonto) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
