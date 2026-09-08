<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileText, CheckCircle, XCircle, Clock, Truck } from 'lucide-vue-next';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    pedido: { type: Object, required: true },
    items: { type: Array, required: true },
});

const money = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0);

const timeline = [
    { key: 'creado', label: 'Creado', at: props.pedido.creado, icon: FileText, done: true },
    { key: 'enviado', label: 'Enviado', at: props.pedido.enviado_at, icon: Truck, done: !!props.pedido.enviado_at },
    { key: 'aprobado', label: 'Aprobado', at: props.pedido.aprobado_at, icon: CheckCircle, done: !!props.pedido.aprobado_at, hidden: props.pedido.estado === 'rechazado' },
    { key: 'rechazado', label: 'Rechazado', at: props.pedido.rechazado_at, icon: XCircle, done: !!props.pedido.rechazado_at, hidden: props.pedido.estado !== 'rechazado' },
    { key: 'facturado', label: 'Facturado', at: props.pedido.facturado_at, icon: FileText, done: !!props.pedido.facturado_at },
].filter(t => !t.hidden);
</script>

<template>
    <Head :title="'Pedido ' + pedido.numero"/>
    <PortalLayout>
        <div class="max-w-4xl mx-auto space-y-4">
            <Link href="/portal/pedidos" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>

            <div class="card p-6">
                <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                    <div>
                        <div class="text-xs text-surface-500 uppercase">Pedido</div>
                        <h1 class="text-2xl font-bold">{{ pedido.numero }}</h1>
                        <div class="text-xs text-surface-500 mt-1">{{ pedido.creado }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-surface-500 uppercase">Total</div>
                        <div class="text-3xl font-bold text-brand-600">{{ money(pedido.total) }}</div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="border-t border-surface-200 dark:border-surface-800 pt-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">Seguimiento</div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <div v-for="(t, idx) in timeline" :key="t.key" class="flex items-center gap-2">
                            <div :class="['h-8 w-8 rounded-full flex items-center justify-center', t.done ? 'bg-emerald-500 text-white' : 'bg-surface-200 text-surface-500']">
                                <component :is="t.icon" class="h-4 w-4"/>
                            </div>
                            <div>
                                <div class="text-sm font-medium">{{ t.label }}</div>
                                <div class="text-[10px] text-surface-500">{{ t.at || '—' }}</div>
                            </div>
                            <div v-if="idx < timeline.length - 1" class="w-6 h-px bg-surface-300 dark:bg-surface-700"></div>
                        </div>
                    </div>
                </div>

                <div v-if="pedido.motivo_rechazo" class="mt-4 p-3 rounded-lg bg-red-50 border-l-4 border-red-500 text-red-700 text-sm">
                    <b>Motivo de rechazo:</b> {{ pedido.motivo_rechazo }}
                </div>

                <div v-if="pedido.factura" class="mt-4 p-3 rounded-lg bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-sm">
                    Facturado con <b>{{ pedido.factura.numero }}</b> · {{ money(pedido.factura.total) }}
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">Ítems ({{ items.length }})</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-surface-500 uppercase">
                            <tr>
                                <th class="text-left p-2">Producto</th>
                                <th class="text-right p-2">Cant.</th>
                                <th class="text-right p-2">Precio</th>
                                <th class="text-right p-2">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="(it, idx) in items" :key="idx">
                                <td class="p-2">
                                    <div class="font-medium">{{ it.desc }}</div>
                                    <div class="text-xs text-surface-500 font-mono">{{ it.sku }}</div>
                                </td>
                                <td class="p-2 text-right">{{ it.cantidad }}</td>
                                <td class="p-2 text-right">{{ money(it.precio) }}</td>
                                <td class="p-2 text-right font-bold">{{ money(it.total) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-surface-200 dark:border-surface-800">
                            <tr><td colspan="3" class="p-2 text-right text-xs text-surface-500">Subtotal</td><td class="p-2 text-right">{{ money(pedido.subtotal) }}</td></tr>
                            <tr><td colspan="3" class="p-2 text-right text-xs text-surface-500">IVA</td><td class="p-2 text-right">{{ money(pedido.iva) }}</td></tr>
                            <tr class="font-bold"><td colspan="3" class="p-2 text-right">Total</td><td class="p-2 text-right text-brand-600">{{ money(pedido.total) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div v-if="pedido.notas_cliente" class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Tus notas</div>
                <p class="text-sm">{{ pedido.notas_cliente }}</p>
            </div>
        </div>
    </PortalLayout>
</template>
