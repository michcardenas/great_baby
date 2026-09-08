<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Calculator, TrendingUp, TrendingDown, CheckCircle, AlertCircle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    filtros: { type: Object, required: true },
    kpis: { type: Object, required: true },
    porCuenta: { type: Array, required: true },
    movimientosRecientes: { type: Array, required: true },
});

const desde = ref(props.filtros.desde);
const hasta = ref(props.filtros.hasta);

const filtrar = () => {
    router.get('/app/contabilidad', { desde: desde.value, hasta: hasta.value }, {
        preserveScroll: true, preserveState: true, replace: true,
    });
};
watch([desde, hasta], filtrar);

const fmtCOP = (n) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO');
const balanceado = Math.abs(props.kpis.balance) < 1;
</script>

<template>
    <Head title="Contabilidad"/>
    <AppLayout>
        <div class="space-y-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Calculator class="h-6 w-6 text-brand-600"/>
                    Contabilidad
                </h1>
                <p class="text-sm text-surface-500 mt-1">Balance de comprobación, movimientos y validación de partida doble.</p>
            </div>

            <!-- Filtros fecha -->
            <div class="card p-4 flex items-center gap-2 flex-wrap">
                <label class="text-sm text-surface-500">Período:</label>
                <input v-model="desde" type="date" class="input"/>
                <span class="text-surface-500">→</span>
                <input v-model="hasta" type="date" class="input"/>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <KpiCard label="Total débitos" :value="kpis.total_debe" color="blue" format="money" :icon="TrendingUp"/>
                <KpiCard label="Total créditos" :value="kpis.total_haber" color="purple" format="money" :icon="TrendingDown"/>
                <div :class="['card p-5 border-l-4', balanceado ? 'border-l-emerald-500' : 'border-l-red-500']">
                    <div class="text-xs uppercase tracking-wider text-surface-500 font-medium">Balance</div>
                    <div :class="['text-3xl font-black mt-1 tabular-nums', balanceado ? 'text-emerald-600' : 'text-red-600']">
                        {{ fmtCOP(Math.abs(kpis.balance)) }}
                    </div>
                    <div class="text-xs mt-1 flex items-center gap-1" :class="balanceado ? 'text-emerald-600' : 'text-red-600'">
                        <CheckCircle v-if="balanceado" class="h-3 w-3"/>
                        <AlertCircle v-else class="h-3 w-3"/>
                        {{ balanceado ? 'Partida doble cuadrada' : 'DESCUADRE en el período' }}
                    </div>
                </div>
                <KpiCard label="Cantidad de asientos" :value="kpis.movimientos" color="amber"/>
            </div>

            <!-- Balance de comprobación -->
            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">📊 Balance de comprobación por cuenta</div>
                <div v-if="!porCuenta.length" class="text-center py-6 text-surface-500 text-sm">Sin movimientos en el período.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[500px] text-sm">
                        <thead>
                            <tr class="text-surface-500 text-xs uppercase border-b border-surface-200 dark:border-surface-800">
                                <th class="text-left py-2">Cuenta PUC</th>
                                <th class="text-right">Débito</th>
                                <th class="text-right">Crédito</th>
                                <th class="text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in porCuenta" :key="c.cuenta_puc" class="border-b border-surface-100 dark:border-surface-900 hover:bg-surface-50 dark:hover:bg-surface-900/30">
                                <td class="py-2 font-mono font-semibold text-brand-600">{{ c.cuenta_puc }}</td>
                                <td class="text-right font-mono">{{ fmtCOP(c.debe) }}</td>
                                <td class="text-right font-mono">{{ fmtCOP(c.haber) }}</td>
                                <td class="text-right font-mono font-bold" :class="c.saldo >= 0 ? 'text-blue-600' : 'text-purple-600'">
                                    {{ fmtCOP(Math.abs(c.saldo)) }} {{ c.saldo >= 0 ? 'D' : 'C' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Movimientos recientes -->
            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">📝 Movimientos recientes ({{ movimientosRecientes.length }})</div>
                <div v-if="!movimientosRecientes.length" class="text-center py-6 text-surface-500 text-sm">Sin movimientos en el período.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[800px] text-sm">
                        <thead>
                            <tr class="text-surface-500 text-xs uppercase border-b border-surface-200 dark:border-surface-800">
                                <th class="text-left py-2">Fecha</th>
                                <th class="text-left">Cuenta</th>
                                <th class="text-left">Descripción</th>
                                <th class="text-left">Origen</th>
                                <th class="text-right">Débe</th>
                                <th class="text-right">Haber</th>
                                <th class="text-left">Por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in movimientosRecientes" :key="m.id" class="border-b border-surface-100 dark:border-surface-900">
                                <td class="py-2 text-xs text-surface-500">{{ m.fecha }}</td>
                                <td class="font-mono text-brand-600">{{ m.cuenta_puc }}</td>
                                <td class="text-xs">{{ m.descripcion }}</td>
                                <td class="text-xs text-surface-500">{{ m.origen }}</td>
                                <td class="text-right font-mono" :class="m.debe > 0 ? 'text-blue-600 font-bold' : 'text-surface-400'">{{ m.debe > 0 ? fmtCOP(m.debe) : '—' }}</td>
                                <td class="text-right font-mono" :class="m.haber > 0 ? 'text-purple-600 font-bold' : 'text-surface-400'">{{ m.haber > 0 ? fmtCOP(m.haber) : '—' }}</td>
                                <td class="text-xs">{{ m.usuario || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
