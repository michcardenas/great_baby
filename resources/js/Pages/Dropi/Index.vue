<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Truck, Package, DollarSign, TrendingUp, AlertCircle, ArrowRight } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';
import { useMoney } from '@/composables/useMoney';
import { usePedidoBadge } from '@/composables/usePedidoBadge';

const props = defineProps({
    tab: { type: String, default: 'dashboard' },
    kpis: { type: Object, required: true },
    porEstado: { type: Array, required: true },
    pedidosRecientes: { type: Array, required: true },
    cortes: { type: Array, required: true },
    devoluciones: { type: Array, required: true },
    wallet: { type: Object, required: true },
    auditorias: { type: Object, required: true },
});
// U6 · quitamos los tabs `cortes`, `devoluciones`, `wallet` que duplicaban las páginas
// dedicadas. Aracely ahora tiene 1 sola fuente de verdad para cada área.
const tabAct = ref(props.tab === 'pedidos' ? 'pedidos' : 'dashboard');
const { money: fmtCOP } = useMoney();
const { badge } = usePedidoBadge();
</script>

<template>
    <Head title="Dropi"/>
    <AppLayout>
        <div class="space-y-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Truck class="h-6 w-6 text-brand-600"/>
                    Dropi
                </h1>
                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Pedidos, cortes, devoluciones, wallet y auditorías de mercancía.</p>
            </div>

            <!-- U14 · KPIs clickables -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <Link href="/app/dropi?tab=pedidos" class="block">
                    <KpiCard label="Pedidos del mes" :value="kpis.pedidos_mes" color="blue" :icon="Package"/>
                </Link>
                <Link href="/app/dropi/alistador?modo=empaque" class="block">
                    <KpiCard label="Pendientes por empacar" :value="kpis.pendientes" color="amber" :icon="AlertCircle"/>
                </Link>
                <Link href="/app/dropi/reportes" class="block">
                    <KpiCard label="Entregados del mes" :value="kpis.entregados_mes" color="emerald" :icon="TrendingUp"/>
                </Link>
                <Link href="/app/dropi/wallet" class="block">
                    <KpiCard label="Ventas del mes" :value="kpis.ventas_mes" color="purple" format="money" :icon="DollarSign"/>
                </Link>
            </div>

            <!-- U34 · Auditorías flotantes clickables -->
            <div v-if="auditorias.fantasma.total > 0 || auditorias.transito.total > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Link v-if="auditorias.fantasma.total > 0" href="/app/dropi/discrepancias" class="card p-4 border-l-4 border-l-red-500 bg-red-500/5 hover:bg-red-500/10 block">
                    <div class="text-xs uppercase font-bold text-red-600 flex items-center justify-between">
                        <span>👻 Mercancía fantasma</span> <ArrowRight class="h-3 w-3"/>
                    </div>
                    <div class="text-2xl font-black text-red-600 mt-1">{{ auditorias.fantasma.total }}</div>
                    <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">devoluciones marcadas por Dropi hace &gt;{{ auditorias.fantasma.dias_tolerancia }}d sin llegar</div>
                </Link>
                <Link v-if="auditorias.transito.total > 0" href="/app/dropi/discrepancias" class="card p-4 border-l-4 border-l-amber-500 bg-amber-500/5 hover:bg-amber-500/10 block">
                    <div class="text-xs uppercase font-bold text-amber-600 flex items-center justify-between">
                        <span>🚚 En tránsito prolongado</span> <ArrowRight class="h-3 w-3"/>
                    </div>
                    <div class="text-2xl font-black text-amber-600 mt-1">{{ auditorias.transito.total }}</div>
                    <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">despachados hace &gt;{{ auditorias.transito.dias_max }}d sin novedad</div>
                </Link>
            </div>

            <!-- U6 · sólo 2 tabs: dashboard vs pedidos recientes. Cortes/wallet/devoluciones en su página dedicada. -->
            <div class="flex items-center gap-2 border-b border-surface-200 dark:border-surface-800 overflow-x-auto">
                <button v-for="t in ['dashboard','pedidos']" :key="t" @click="tabAct=t"
                        :class="['px-4 py-2 text-sm font-semibold border-b-2 capitalize whitespace-nowrap', tabAct===t ? 'border-brand-500 text-brand-600' : 'border-transparent text-surface-500 dark:text-surface-400']">
                    {{ t }}
                </button>
                <div class="ml-auto flex items-center gap-2 pr-2">
                    <Link href="/app/dropi/cortes" class="text-xs text-brand-600 hover:underline">Cortes →</Link>
                    <Link href="/app/dropi/wallet" class="text-xs text-brand-600 hover:underline">Wallet →</Link>
                    <Link href="/app/dropi/discrepancias" class="text-xs text-brand-600 hover:underline">Discrepancias →</Link>
                </div>
            </div>

            <!-- Dashboard -->
            <div v-if="tabAct==='dashboard'" class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">Distribución por estado</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <div v-for="e in porEstado" :key="e.estado" class="p-3 rounded-lg bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-800">
                        <div class="text-xs uppercase text-surface-500 dark:text-surface-400">{{ e.label }}</div>
                        <div class="text-2xl font-black text-brand-600 mt-1">{{ e.total }}</div>
                    </div>
                </div>
            </div>

            <!-- Pedidos -->
            <div v-if="tabAct==='pedidos'" class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900"><tr class="text-surface-500 dark:text-surface-400 text-xs uppercase">
                            <th class="text-left px-4 py-2">Guía</th>
                            <th class="text-left">Cliente</th>
                            <th class="text-left">Ciudad</th>
                            <th class="text-left">Transportadora</th>
                            <th class="text-left">Corte</th>
                            <th class="text-center">Estado</th>
                            <th class="text-right">Monto</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="p in pedidosRecientes" :key="p.id" class="border-t border-surface-100 dark:border-surface-900">
                                <td class="px-4 py-2 font-mono text-brand-600 font-semibold">{{ p.guia }}</td>
                                <td>{{ p.cliente }}</td>
                                <td class="text-xs">{{ p.ciudad }}</td>
                                <td class="text-xs">{{ p.transportadora }}</td>
                                <td class="text-xs">{{ p.corte || '—' }}</td>
                                <td class="text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="badge(p.estado ?? p.estado_label).cls">
                                        {{ badge(p.estado ?? p.estado_label).label }}
                                    </span>
                                </td>
                                <td class="text-right font-mono">{{ fmtCOP(p.monto) }}</td>
                            </tr>
                            <!-- Re-audit UX#8 · empty state para pedidosRecientes -->
                            <tr v-if="!pedidosRecientes.length">
                                <td colspan="7" class="p-6 text-center text-sm text-surface-500">
                                    Sin pedidos recientes en el corte activo.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 text-center border-t border-surface-200 dark:border-surface-800">
                    <Link href="/app/dropi/alistador?modo=empaque" class="text-xs font-semibold text-brand-600 hover:underline">
                        Ver todos los pedidos por empacar →
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
