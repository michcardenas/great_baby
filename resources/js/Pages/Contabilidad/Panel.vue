<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Calculator, TrendingUp, TrendingDown, AlertTriangle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ periodo: Object, kpis: Object, topCuentas: Array, porOrigen: Array });
const desde = ref(props.periodo.desde);
const hasta = ref(props.periodo.hasta);
const filtrar = () => router.get('/app/contabilidad/panel', { desde: desde.value, hasta: hasta.value });
const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
</script>

<template>
    <Head title="Panel contable"/>
    <AppLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2"><Calculator class="h-6 w-6 text-brand-600"/>Panel contable</h1>

            <div class="card p-3 flex items-center gap-2">
                <div><label class="text-xs">Desde</label><input type="date" v-model="desde" class="input"/></div>
                <div><label class="text-xs">Hasta</label><input type="date" v-model="hasta" class="input"/></div>
                <button @click="filtrar" class="btn-primary self-end">Aplicar</button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card p-4"><div class="text-xs uppercase text-surface-500 flex items-center gap-1"><TrendingUp class="h-3 w-3"/>Débito</div><div class="text-xl font-bold mt-1 text-emerald-600">{{ money(kpis.total_debe) }}</div></div>
                <div class="card p-4"><div class="text-xs uppercase text-surface-500 flex items-center gap-1"><TrendingDown class="h-3 w-3"/>Crédito</div><div class="text-xl font-bold mt-1 text-red-600">{{ money(kpis.total_haber) }}</div></div>
                <div class="card p-4" :class="kpis.unbalanced ? 'ring-2 ring-red-500' : ''">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-1">
                        <AlertTriangle v-if="kpis.unbalanced" class="h-3 w-3 text-red-600"/>Diferencia
                    </div>
                    <div class="text-xl font-bold mt-1" :class="kpis.unbalanced ? 'text-red-600' : 'text-emerald-600'">{{ money(kpis.balance) }}</div>
                </div>
                <div class="card p-4"><div class="text-xs uppercase text-surface-500">Movimientos</div><div class="text-3xl font-bold mt-1">{{ kpis.movimientos }}</div></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-2">Top 10 cuentas movidas</div>
                    <table class="w-full text-sm">
                        <thead class="text-[10px] text-surface-500 uppercase border-b">
                            <tr><th class="text-left p-2">Cuenta</th><th class="text-right p-2">Débito</th><th class="text-right p-2">Crédito</th><th class="text-right p-2">Movs</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="c in topCuentas" :key="c.cuenta">
                                <td class="p-2 font-mono">{{ c.cuenta }}</td>
                                <td class="p-2 text-right text-emerald-600">{{ money(c.debe) }}</td>
                                <td class="p-2 text-right text-red-600">{{ money(c.haber) }}</td>
                                <td class="p-2 text-right">{{ c.movs }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-2">Por origen del asiento</div>
                    <table class="w-full text-sm">
                        <thead class="text-[10px] text-surface-500 uppercase border-b">
                            <tr><th class="text-left p-2">Origen</th><th class="text-right p-2">Docs</th><th class="text-right p-2">Total</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="o in porOrigen" :key="o.origen">
                                <td class="p-2">{{ o.origen }}</td>
                                <td class="p-2 text-right">{{ o.docs }}</td>
                                <td class="p-2 text-right font-bold">{{ money(o.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
