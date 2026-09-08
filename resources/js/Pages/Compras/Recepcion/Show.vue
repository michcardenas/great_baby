<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Truck } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ recepcion: { type: Object, required: true } });
const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
</script>

<template>
    <Head :title="`Recepción ${recepcion.numero}`"/>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-4">
            <Link href="/app/compras" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>

            <div class="card p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xs text-surface-500 uppercase">Recepción</div>
                        <h1 class="text-2xl font-bold font-mono">{{ recepcion.numero }}</h1>
                        <div class="text-xs text-surface-500 mt-1">OC: {{ recepcion.orden_numero }} · {{ recepcion.fecha_recepcion }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-surface-500 uppercase">Total recibido</div>
                        <div class="text-2xl font-bold text-brand-600">{{ money(recepcion.total_recibido) }}</div>
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">{{ recepcion.estado }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4 text-sm border-t pt-3">
                    <div><b>Remisión:</b> {{ recepcion.remision_proveedor || '—' }}</div>
                    <div><b>Factura:</b> {{ recepcion.factura_proveedor || '—' }}</div>
                    <div><b>Transportista:</b> {{ recepcion.transportista || '—' }}</div>
                </div>
                <a v-if="recepcion.id" :href="`/compras/recepcion/${recepcion.id}/pdf`" target="_blank" class="btn-ghost mt-4">PDF Recepción</a>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase font-bold text-brand-600 mb-2">Ítems ({{ recepcion.items.length }})</div>
                <table class="w-full text-sm">
                    <thead class="text-[10px] text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-right p-2">Cantidad</th>
                            <th class="text-right p-2">Costo unit</th>
                            <th class="text-right p-2">Subtotal</th>
                            <th class="text-left p-2">Lote</th>
                            <th class="text-left p-2">Obs</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="it in recepcion.items" :key="it.id">
                            <td class="p-2 text-right">{{ it.cantidad_recibida }}</td>
                            <td class="p-2 text-right">{{ money(it.costo_unit) }}</td>
                            <td class="p-2 text-right font-bold">{{ money(it.subtotal) }}</td>
                            <td class="p-2 text-xs">{{ it.lote || '—' }}</td>
                            <td class="p-2 text-xs">{{ it.observaciones || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="recepcion.observaciones" class="card p-4">
                <div class="text-xs uppercase font-bold text-brand-600 mb-2">Observaciones</div>
                <p class="text-sm whitespace-pre-wrap">{{ recepcion.observaciones }}</p>
            </div>
        </div>
    </AppLayout>
</template>
