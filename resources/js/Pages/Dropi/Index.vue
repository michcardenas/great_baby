<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Truck, Package, DollarSign, TrendingUp, AlertCircle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

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
const tabAct = ref(props.tab);
const fmtCOP = (n) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO');
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
                <p class="text-sm text-surface-500 mt-1">Pedidos, cortes, devoluciones, wallet y auditorías de mercancía.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <KpiCard label="Pedidos del mes" :value="kpis.pedidos_mes" color="blue" :icon="Package"/>
                <KpiCard label="Pendientes por empacar" :value="kpis.pendientes" color="amber" :icon="AlertCircle"/>
                <KpiCard label="Entregados del mes" :value="kpis.entregados_mes" color="emerald" :icon="TrendingUp"/>
                <KpiCard label="Ventas del mes" :value="kpis.ventas_mes" color="purple" format="money" :icon="DollarSign"/>
            </div>

            <!-- Auditorías flotantes si hay -->
            <div v-if="auditorias.fantasma.total > 0 || auditorias.transito.total > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div v-if="auditorias.fantasma.total > 0" class="card p-4 border-l-4 border-l-red-500 bg-red-500/5">
                    <div class="text-xs uppercase font-bold text-red-600">👻 Mercancía fantasma</div>
                    <div class="text-2xl font-black text-red-600 mt-1">{{ auditorias.fantasma.total }}</div>
                    <div class="text-xs text-surface-500 mt-1">devoluciones marcadas por Dropi hace &gt;{{ auditorias.fantasma.dias_tolerancia }}d sin llegar</div>
                </div>
                <div v-if="auditorias.transito.total > 0" class="card p-4 border-l-4 border-l-amber-500 bg-amber-500/5">
                    <div class="text-xs uppercase font-bold text-amber-600">🚚 En tránsito prolongado</div>
                    <div class="text-2xl font-black text-amber-600 mt-1">{{ auditorias.transito.total }}</div>
                    <div class="text-xs text-surface-500 mt-1">despachados hace &gt;{{ auditorias.transito.dias_max }}d sin novedad</div>
                </div>
            </div>

            <div class="flex items-center gap-2 border-b border-surface-200 dark:border-surface-800 overflow-x-auto">
                <button v-for="t in ['dashboard','pedidos','cortes','devoluciones','wallet']" :key="t" @click="tabAct=t"
                        :class="['px-4 py-2 text-sm font-semibold border-b-2 capitalize whitespace-nowrap', tabAct===t ? 'border-brand-500 text-brand-600' : 'border-transparent text-surface-500']">
                    {{ t }}
                </button>
            </div>

            <!-- Dashboard -->
            <div v-if="tabAct==='dashboard'" class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">Distribución por estado</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <div v-for="e in porEstado" :key="e.estado" class="p-3 rounded-lg bg-surface-50 dark:bg-surface-900 border border-surface-200 dark:border-surface-800">
                        <div class="text-xs uppercase text-surface-500">{{ e.label }}</div>
                        <div class="text-2xl font-black text-brand-600 mt-1">{{ e.total }}</div>
                    </div>
                </div>
            </div>

            <!-- Pedidos -->
            <div v-if="tabAct==='pedidos'" class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900"><tr class="text-surface-500 text-xs uppercase">
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
                                <td class="text-center"><span class="text-xs font-bold">{{ p.estado_label }}</span></td>
                                <td class="text-right font-mono">{{ fmtCOP(p.monto) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cortes -->
            <div v-if="tabAct==='cortes'" class="card overflow-hidden">
                <div v-if="!cortes.length" class="text-center py-10 text-surface-500 text-sm">Sin cortes registrados.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900"><tr class="text-surface-500 text-xs uppercase">
                            <th class="text-left px-4 py-2">Número</th>
                            <th class="text-left">Desde</th>
                            <th class="text-left">Hasta</th>
                            <th class="text-center">Estado</th>
                            <th class="text-right">Pedidos</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="c in cortes" :key="c.id" class="border-t border-surface-100 dark:border-surface-900">
                                <td class="px-4 py-2 font-mono font-semibold text-brand-600">{{ c.numero }}</td>
                                <td class="text-xs">{{ c.fecha_desde }}</td>
                                <td class="text-xs">{{ c.fecha_hasta }}</td>
                                <td class="text-center capitalize">{{ c.estado }}</td>
                                <td class="text-right font-bold">{{ c.pedidos_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Devoluciones -->
            <div v-if="tabAct==='devoluciones'" class="card overflow-hidden">
                <div v-if="!devoluciones.length" class="text-center py-10 text-surface-500 text-sm">Sin devoluciones registradas.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900"><tr class="text-surface-500 text-xs uppercase">
                            <th class="text-left px-4 py-2">Recibido</th>
                            <th class="text-left">Guía</th>
                            <th class="text-left">Cliente</th>
                            <th class="text-left">Destino</th>
                            <th class="text-center">NC</th>
                            <th class="text-left">Notas</th>
                            <th class="text-left">Por</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="d in devoluciones" :key="d.id" class="border-t border-surface-100 dark:border-surface-900">
                                <td class="px-4 py-2 text-xs text-surface-500">{{ d.recibido }}</td>
                                <td class="font-mono text-brand-600">{{ d.guia }}</td>
                                <td>{{ d.cliente }}</td>
                                <td class="capitalize text-xs">{{ d.destino }}</td>
                                <td class="text-center">
                                    <span v-if="d.nc" class="text-emerald-600 text-lg">✓</span>
                                    <span v-else class="text-surface-400">—</span>
                                </td>
                                <td class="text-xs text-surface-500 max-w-xs truncate">{{ d.notas || '—' }}</td>
                                <td class="text-xs">{{ d.por || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Wallet -->
            <div v-if="tabAct==='wallet'" class="space-y-3">
                <div class="card p-4 bg-gradient-to-br from-emerald-500/10 to-brand-500/10">
                    <div class="text-xs uppercase tracking-widest font-bold text-emerald-600">💰 Saldo Wallet Dropi</div>
                    <div class="text-4xl font-black text-emerald-600 mt-1 tabular-nums">{{ fmtCOP(wallet.saldo) }}</div>
                </div>
                <div class="card overflow-hidden">
                    <div v-if="!wallet.movimientos.length" class="text-center py-10 text-surface-500 text-sm">Sin movimientos.</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[600px] text-sm">
                            <thead class="bg-surface-50 dark:bg-surface-900"><tr class="text-surface-500 text-xs uppercase">
                                <th class="text-left px-4 py-2">Fecha</th>
                                <th class="text-left">Tipo</th>
                                <th class="text-left">Ref</th>
                                <th class="text-left">Descripción</th>
                                <th class="text-right">Monto</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="m in wallet.movimientos" :key="m.id" class="border-t border-surface-100 dark:border-surface-900">
                                    <td class="px-4 py-2 text-surface-500">{{ m.fecha }}</td>
                                    <td class="text-xs uppercase font-semibold">{{ m.tipo }}</td>
                                    <td class="text-xs font-mono">{{ m.referencia || '—' }}</td>
                                    <td class="text-xs">{{ m.descripcion || '—' }}</td>
                                    <td class="text-right font-bold font-mono" :class="m.monto >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                        {{ fmtCOP(m.monto) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
