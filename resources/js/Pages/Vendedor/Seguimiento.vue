<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Clock, DollarSign, History, Eye, CheckCircle2 } from 'lucide-vue-next';

defineProps({
    pendientes: Array,
    por_cobrar: Array,
    ultimos: Array,
    totales: Object,
    es_super: Boolean,
});

const money = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0);

const estadoChip = (e) => ({
    borrador: 'bg-surface-200 text-surface-700',
    enviado: 'bg-blue-100 text-blue-800',
    retenido: 'bg-amber-100 text-amber-900 ring-1 ring-amber-400',
    aprobado: 'bg-emerald-100 text-emerald-800',
    facturado: 'bg-fuchsia-100 text-fuchsia-800',
    despachado: 'bg-indigo-100 text-indigo-800',
    rechazado: 'bg-red-100 text-red-800',
}[e] || 'bg-surface-100');

const fechaCorta = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
};
</script>

<template>
    <Head title="Seguimiento de pedidos"/>
    <AppLayout>
        <div class="space-y-5 max-w-7xl mx-auto">

            <div>
                <h1 class="text-2xl font-bold">Seguimiento de pedidos</h1>
                <p class="text-sm text-surface-500 mt-1">Qué hay abierto, qué está por cobrar y los últimos movimientos.</p>
            </div>

            <!-- Pedidos pendientes (sin facturar) -->
            <div class="card overflow-hidden">
                <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="font-semibold flex items-center gap-2">
                        <Clock class="h-5 w-5 text-surface-500"/>
                        Pedidos sin facturar
                    </h3>
                    <span class="px-2 py-0.5 bg-surface-200 dark:bg-surface-700 text-xs rounded font-semibold">
                        {{ totales.pendientes }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-surface-500 bg-surface-50 dark:bg-surface-900">
                            <tr>
                                <th class="p-3 text-left">N.º</th>
                                <th class="p-3 text-left">Cliente</th>
                                <th class="p-3 text-left">Estado</th>
                                <th class="p-3 text-right">Monto</th>
                                <th class="p-3 text-left">Creado</th>
                                <th class="p-3 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-if="!pendientes.length">
                                <td colspan="6" class="text-center text-surface-400 py-8">
                                    <CheckCircle2 class="h-7 w-7 mx-auto mb-2 text-emerald-500"/>
                                    No tienes pedidos pendientes. ¡Todo al día!
                                </td>
                            </tr>
                            <tr v-for="p in pendientes" :key="p.id" class="hover:bg-surface-50 dark:hover:bg-surface-900">
                                <td class="p-3 font-mono text-xs font-bold">{{ p.numero }}</td>
                                <td class="p-3">
                                    <div>{{ p.cliente }}</div>
                                    <div v-if="p.motivo_retencion" class="text-[10px] text-amber-700 italic mt-0.5">
                                        ⏸ {{ p.motivo_retencion }}
                                    </div>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold" :class="estadoChip(p.estado)">
                                        {{ p.estado }}
                                    </span>
                                </td>
                                <td class="p-3 text-right font-bold">{{ money(p.total) }}</td>
                                <td class="p-3 text-xs text-surface-600">{{ p.fecha }}</td>
                                <td class="p-3 text-center">
                                    <Link :href="`/app/pedidos-b2b/${p.id}`"
                                          class="inline-flex items-center gap-1 px-2 py-1 bg-brand-50 hover:bg-brand-100 text-brand-700 rounded text-xs font-semibold">
                                        <Eye class="h-3 w-3"/> Ver
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagos por cobrar -->
            <div class="card overflow-hidden">
                <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="font-semibold flex items-center gap-2">
                        <DollarSign class="h-5 w-5 text-amber-600"/>
                        Pagos por cobrar
                    </h3>
                    <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded font-semibold">
                        {{ money(totales.por_cobrar) }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-surface-500 bg-surface-50 dark:bg-surface-900">
                            <tr>
                                <th class="p-3 text-left">Pedido</th>
                                <th class="p-3 text-left">Factura</th>
                                <th class="p-3 text-left">Cliente</th>
                                <th class="p-3 text-right">Saldo</th>
                                <th class="p-3 text-left">Facturado</th>
                                <th class="p-3 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-if="!por_cobrar.length">
                                <td colspan="6" class="text-center text-surface-400 py-8">
                                    <CheckCircle2 class="h-7 w-7 mx-auto mb-2 text-emerald-500"/>
                                    No tienes pagos por cobrar. ¡Todo cobrado!
                                </td>
                            </tr>
                            <tr v-for="p in por_cobrar" :key="p.id" class="hover:bg-amber-50/40 dark:hover:bg-amber-950/20 bg-amber-50/20">
                                <td class="p-3 font-mono text-xs font-bold">{{ p.numero }}</td>
                                <td class="p-3 text-xs text-surface-600">{{ p.factura || '—' }}</td>
                                <td class="p-3">{{ p.cliente }}</td>
                                <td class="p-3 text-right font-bold text-amber-700">{{ money(p.saldo_factura) }}</td>
                                <td class="p-3 text-xs text-surface-600">{{ p.facturado_at || '—' }}</td>
                                <td class="p-3 text-center">
                                    <Link :href="`/app/pedidos-b2b/${p.id}`"
                                          class="inline-flex items-center gap-1 px-2 py-1 bg-brand-50 hover:bg-brand-100 text-brand-700 rounded text-xs font-semibold">
                                        <Eye class="h-3 w-3"/> Ver
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Últimos movimientos -->
            <div class="card overflow-hidden">
                <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-800 flex items-center justify-between">
                    <h3 class="font-semibold flex items-center gap-2">
                        <History class="h-5 w-5 text-brand-600"/>
                        Últimos movimientos
                    </h3>
                    <span class="px-2 py-0.5 bg-brand-100 text-brand-800 text-xs rounded font-semibold">
                        {{ totales.ultimos }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-surface-500 bg-surface-50 dark:bg-surface-900">
                            <tr>
                                <th class="p-3 text-left">N.º</th>
                                <th class="p-3 text-left">Cliente</th>
                                <th class="p-3 text-right">Monto</th>
                                <th class="p-3 text-left">Estado</th>
                                <th class="p-3 text-left">Fecha</th>
                                <th class="p-3 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-if="!ultimos.length">
                                <td colspan="6" class="text-center text-surface-400 py-6 italic">
                                    Sin movimientos recientes
                                </td>
                            </tr>
                            <tr v-for="p in ultimos" :key="p.id" class="hover:bg-surface-50 dark:hover:bg-surface-900">
                                <td class="p-3 font-mono text-xs">{{ p.numero }}</td>
                                <td class="p-3">{{ p.cliente }}</td>
                                <td class="p-3 text-right font-bold">{{ money(p.total) }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold" :class="estadoChip(p.estado)">
                                        {{ p.estado }}
                                    </span>
                                </td>
                                <td class="p-3 text-xs text-surface-600">{{ p.fecha }}</td>
                                <td class="p-3 text-center">
                                    <Link :href="`/app/pedidos-b2b/${p.id}`"
                                          class="inline-flex items-center gap-1 px-2 py-1 bg-brand-50 hover:bg-brand-100 text-brand-700 rounded text-xs font-semibold">
                                        <Eye class="h-3 w-3"/> Ver
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
