<script setup>
import { ref, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { useMoney } from '@/composables/useMoney';
import { useDebounceFn } from '@vueuse/core';
import { CreditCard, Search, Calendar, Upload } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    pagos: { type: Object, required: true },
    filtros: { type: Object, required: true },
    totales: { type: Object, required: true },
    mediosPago: { type: Array, required: true },
});

const q = ref(props.filtros.q || '');
const medio = ref(props.filtros.medio || 'todos');
const desde = ref(props.filtros.desde || '');
const hasta = ref(props.filtros.hasta || '');

const filtrar = () => {
    router.get('/app/pagos', { q: q.value, medio: medio.value, desde: desde.value, hasta: hasta.value }, {
        preserveScroll: true, preserveState: true, replace: true,
    });
};
const buscarDebounced = useDebounceFn(filtrar, 400);
watch(q, buscarDebounced);
watch([medio, desde, hasta], filtrar);

const { money: fmtCOP } = useMoney();

const badgeDif = (clas, dif) => {
    if (! dif || Math.abs(dif) < 1) return { txt: 'Exacto', cls: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' };
    if (clas === 'sobrepago') return { txt: 'Sobrepago', cls: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200' };
    if (clas === 'diferencia_menor') return { txt: 'Ajuste', cls: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' };
    if (dif < 0) return { txt: 'Faltante', cls: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200' };
    return { txt: clas || '—', cls: 'bg-slate-100 text-slate-700' };
};
</script>

<template>
    <Head title="Pagos"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <CreditCard class="h-6 w-6 text-brand-600"/>
                        Pagos recibidos
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">Consultá pagos aplicados a facturas y clasificación de diferencias.</p>
                </div>
                <!-- H7 · CTA importar extracto bancario (abre el modal del admin Filament ya funcional). -->
                <a href="/admin/pago-ventas" target="_blank" rel="noopener" class="btn-primary text-sm">
                    <Upload class="h-4 w-4"/> Importar extracto bancario
                </a>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <KpiCard label="Total aplicado" :value="totales.aplicado" color="emerald" format="money"/>
                <KpiCard label="Total recibido" :value="totales.recibido" color="blue" format="money"/>
                <KpiCard label="Cantidad de pagos" :value="totales.count" color="amber"/>
            </div>

            <!-- Filtros -->
            <div class="card p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="relative md:col-span-2">
                    <Search class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-surface-400"/>
                    <input v-model="q" type="search" placeholder="Buscar referencia, banco, factura o cliente…" class="input w-full pl-10"/>
                </div>
                <select v-model="medio" class="input">
                    <option value="todos">Todos los medios</option>
                    <option v-for="m in mediosPago" :key="m" :value="m">{{ m }}</option>
                </select>
                <div class="flex gap-2">
                    <input v-model="desde" type="date" class="input flex-1" title="Desde"/>
                    <input v-model="hasta" type="date" class="input flex-1" title="Hasta"/>
                </div>
            </div>

            <!-- Tabla -->
            <div class="card overflow-hidden">
                <div v-if="! pagos.data.length" class="text-center py-16 text-surface-500">
                    <CreditCard class="h-10 w-10 mx-auto opacity-40"/>
                    <div class="text-sm mt-2">Sin pagos con estos criterios.</div>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="bg-surface-50 dark:bg-surface-900">
                            <tr class="text-surface-500 text-xs uppercase">
                                <th class="text-left px-4 py-2">Fecha</th>
                                <th class="text-left">Factura</th>
                                <th class="text-left">Cliente</th>
                                <th class="text-left">Medio</th>
                                <th class="text-left">Referencia</th>
                                <th class="text-right">Recibido</th>
                                <th class="text-right">Aplicado</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in pagos.data" :key="p.id" class="border-t border-surface-100 dark:border-surface-900 hover:bg-surface-50 dark:hover:bg-surface-900/30">
                                <td class="px-4 py-2 text-surface-500">{{ p.fecha }}</td>
                                <td class="font-mono text-brand-600">
                                    <Link v-if="p.factura_id" :href="'/app/facturas/' + p.factura_id" class="hover:underline">{{ p.factura_numero }}</Link>
                                    <span v-else>—</span>
                                </td>
                                <td class="text-surface-800 dark:text-surface-200">{{ p.cliente || '—' }}</td>
                                <td class="capitalize">{{ p.medio_pago || '—' }}</td>
                                <td class="font-mono text-xs text-surface-500">
                                    <div>{{ p.referencia || '—' }}</div>
                                    <div v-if="p.banco" class="text-[10px]">{{ p.banco }}</div>
                                </td>
                                <td class="text-right font-mono">{{ fmtCOP(p.monto_recibido) }}</td>
                                <td class="text-right font-mono font-bold text-emerald-600">{{ fmtCOP(p.monto_aplicado) }}</td>
                                <td class="text-center">
                                    <span :class="['inline-block px-2 py-0.5 rounded text-xs font-bold', badgeDif(p.clasificacion, p.diferencia).cls]">
                                        {{ badgeDif(p.clasificacion, p.diferencia).txt }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pagos.data.length" class="flex items-center justify-between px-4 py-3 border-t border-surface-200 dark:border-surface-800 text-sm">
                    <div class="text-surface-500">{{ pagos.from }}–{{ pagos.to }} de {{ pagos.total }}</div>
                    <div class="flex items-center gap-1">
                        <template v-for="link in pagos.links" :key="link.label">
                            <Link v-if="link.url" :href="link.url"
                                  :class="['px-2 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800']"
                                  v-html="link.label"/>
                            <span v-else class="px-2 py-1 rounded text-xs text-surface-400" v-html="link.label"/>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
