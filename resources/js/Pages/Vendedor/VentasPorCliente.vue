<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Users, DollarSign, TrendingUp, Trophy } from 'lucide-vue-next';

const props = defineProps({
    periodo: String,
    rango: Object,
    clientes: Array,
    es_super: Boolean,
});

const money = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0);
const cambiarPeriodo = (p) => router.get('/app/vendedor/ventas-por-cliente', { periodo: p }, { preserveState: true });

const periodos = [
    { key: 'mes-actual', label: 'Mes actual' },
    { key: 'mes-anterior', label: 'Mes anterior' },
    { key: 'trimestre-actual', label: 'Trimestre' },
    { key: 'anio-actual', label: 'Año' },
];

const totalGeneral = computed(() => props.clientes.reduce((s, c) => s + (c.monto || 0), 0));
const pedidosGeneral = computed(() => props.clientes.reduce((s, c) => s + (c.pedidos || 0), 0));
const promedio = computed(() => props.clientes.length ? totalGeneral.value / props.clientes.length : 0);
</script>

<template>
    <Head title="Mis Ventas por Cliente"/>
    <AppLayout>
        <div class="space-y-5 max-w-6xl mx-auto">
            <!-- Encabezado + presets -->
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Users class="h-6 w-6 text-brand-600"/>
                        Mis ventas por cliente
                    </h1>
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

            <!-- 3 KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs uppercase font-semibold text-surface-500">Clientes con compras</div>
                        <Users class="h-4 w-4 text-fuchsia-600"/>
                    </div>
                    <div class="text-2xl font-black text-fuchsia-700">{{ clientes.length }}</div>
                </div>
                <div class="card p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs uppercase font-semibold text-surface-500">Total facturado</div>
                        <DollarSign class="h-4 w-4 text-emerald-600"/>
                    </div>
                    <div class="text-2xl font-black text-emerald-700">{{ money(totalGeneral) }}</div>
                    <div class="text-xs text-surface-500 mt-1">{{ pedidosGeneral }} pedidos</div>
                </div>
                <div class="card p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs uppercase font-semibold text-surface-500">Promedio por cliente</div>
                        <TrendingUp class="h-4 w-4 text-amber-600"/>
                    </div>
                    <div class="text-2xl font-black text-amber-700">{{ money(promedio) }}</div>
                </div>
            </div>

            <!-- Ranking -->
            <div class="card overflow-hidden">
                <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-800 flex items-center gap-2">
                    <Trophy class="h-5 w-5 text-amber-500"/>
                    <h3 class="font-semibold">Ranking de clientes por ventas</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-surface-500 bg-surface-50 dark:bg-surface-900 border-b border-surface-200 dark:border-surface-800">
                            <tr>
                                <th class="p-3 text-left" style="width:60px">#</th>
                                <th class="p-3 text-left">Cliente</th>
                                <th class="p-3 text-left">Ciudad</th>
                                <th class="p-3 text-center">Pedidos</th>
                                <th class="p-3 text-right">Total facturado</th>
                                <th class="p-3 text-center">Última compra</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-if="!clientes.length">
                                <td colspan="6" class="text-center text-surface-400 py-10">
                                    <div class="text-3xl mb-2">📥</div>
                                    Sin ventas por cliente en este período
                                </td>
                            </tr>
                            <tr v-for="(c, i) in clientes" :key="c.contacto_id" class="hover:bg-surface-50 dark:hover:bg-surface-900">
                                <td class="p-3">
                                    <span v-if="i === 0" class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-800 rounded font-bold">
                                        <Trophy class="h-3 w-3"/>
                                    </span>
                                    <span v-else class="font-mono text-xs text-surface-500">{{ i + 1 }}</span>
                                </td>
                                <td class="p-3 font-semibold">{{ c.cliente }}</td>
                                <td class="p-3 text-xs text-surface-600">{{ c.ciudad || '—' }}</td>
                                <td class="p-3 text-center">
                                    <span class="px-2 py-0.5 bg-surface-200 dark:bg-surface-700 text-surface-700 dark:text-surface-200 rounded text-xs font-semibold">
                                        {{ c.pedidos }}
                                    </span>
                                </td>
                                <td class="p-3 text-right font-bold">{{ money(c.monto) }}</td>
                                <td class="p-3 text-center text-xs text-surface-600">{{ c.ultima_compra || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
