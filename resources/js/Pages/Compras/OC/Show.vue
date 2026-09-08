<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, XCircle, Truck, ShoppingCart } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ orden: { type: Object, required: true } });

const modalAnular = ref(false);
const motivo = ref('');
const procesando = ref(false);

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });

const aprobar = () => {
    if (!confirm('¿Aprobar esta OC?')) return;
    procesando.value = true;
    router.post(`/app/compras/oc/${props.orden.id}/aprobar`, {}, { onFinish: () => procesando.value = false });
};
const anular = () => {
    if (motivo.value.trim().length < 10) return;
    procesando.value = true;
    router.post(`/app/compras/oc/${props.orden.id}/anular`, { motivo: motivo.value }, {
        onSuccess: () => { modalAnular.value = false; motivo.value = ''; },
        onFinish: () => procesando.value = false,
    });
};

const badge = (e) => ({
    borrador: 'bg-surface-100 text-surface-700',
    enviada: 'bg-blue-100 text-blue-800',
    aprobada: 'bg-emerald-100 text-emerald-800',
    parcial: 'bg-amber-100 text-amber-800',
    recibida: 'bg-brand-100 text-brand-800',
    cerrada: 'bg-surface-200 text-surface-600',
    anulada: 'bg-red-100 text-red-800',
}[e] || 'bg-surface-100');
</script>

<template>
    <Head :title="`OC ${orden.numero}`"/>
    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-4">
            <Link href="/app/compras" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div class="card p-5">
                <div class="flex items-start justify-between mb-3 flex-wrap gap-3">
                    <div>
                        <div class="text-xs text-surface-500 uppercase">Orden de compra</div>
                        <h1 class="text-2xl font-bold font-mono">{{ orden.numero }}</h1>
                        <div class="text-xs text-surface-500 mt-1">{{ orden.proveedor }} · {{ orden.tipo }} · {{ orden.moneda }} @ {{ orden.tasa_cambio }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-surface-500 uppercase">Total</div>
                        <div class="text-2xl font-bold text-brand-600">{{ money(orden.total) }}</div>
                        <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(orden.estado)]">{{ orden.estado }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 border-t pt-3">
                    <button v-if="orden.estado === 'borrador' || orden.estado === 'enviada'" @click="aprobar" :disabled="procesando" class="btn-primary">
                        <CheckCircle class="h-4 w-4"/> Aprobar
                    </button>
                    <Link v-if="['aprobada','parcial'].includes(orden.estado)" :href="`/app/compras/recepcion/nueva?oc=${orden.id}`" class="btn-primary bg-emerald-600 hover:bg-emerald-700">
                        <Truck class="h-4 w-4"/> Recibir mercancía
                    </Link>
                    <button v-if="!['recibida','cerrada','anulada'].includes(orden.estado)" @click="modalAnular = true" class="btn-ghost text-red-600 border border-red-300">
                        <XCircle class="h-4 w-4"/> Anular
                    </button>
                    <a :href="`/compras/orden/${orden.id}/pdf`" target="_blank" class="btn-ghost">PDF</a>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">Ítems ({{ orden.items.length }})</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-[10px] text-surface-500 uppercase border-b">
                            <tr>
                                <th class="text-left p-2">Descripción</th>
                                <th class="text-right p-2">Cant</th>
                                <th class="text-right p-2">Recibida</th>
                                <th class="text-right p-2">Precio</th>
                                <th class="text-right p-2">Subtotal</th>
                                <th class="text-right p-2">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="it in orden.items" :key="it.id">
                                <td class="p-2">{{ it.descripcion }}</td>
                                <td class="p-2 text-right">{{ it.cantidad }}</td>
                                <td class="p-2 text-right" :class="it.cantidad_recibida < it.cantidad ? 'text-amber-600' : 'text-emerald-600'">{{ it.cantidad_recibida }}</td>
                                <td class="p-2 text-right">{{ money(it.precio_unit) }}</td>
                                <td class="p-2 text-right">{{ money(it.subtotal) }}</td>
                                <td class="p-2 text-right font-bold">{{ money(it.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="orden.observaciones" class="card p-4">
                <div class="text-xs uppercase font-bold text-brand-600 mb-2">Observaciones</div>
                <p class="text-sm whitespace-pre-wrap">{{ orden.observaciones }}</p>
            </div>
        </div>

        <div v-if="modalAnular" @click.self="modalAnular = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Anular OC</h3>
                <textarea v-model="motivo" rows="3" class="input w-full" placeholder="Motivo mínimo 10 caracteres..." autofocus/>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modalAnular = false" class="btn-ghost">Cancelar</button>
                    <button @click="anular" :disabled="procesando || motivo.trim().length < 10" class="btn-primary bg-red-600 hover:bg-red-700">Anular</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
