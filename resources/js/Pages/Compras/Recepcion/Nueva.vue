<script setup>
import { reactive, ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Truck, Save, ArrowLeft } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ oc: { type: Object, default: null } });

const form = reactive({
    orden_id: props.oc?.id ?? '',
    remision_proveedor: '',
    factura_proveedor: '',
    transportista: '',
    observaciones: '',
    items: props.oc?.items.map(i => ({
        orden_item_id: i.id,
        descripcion: i.descripcion,
        cantidad_pendiente: i.cantidad_pendiente,
        cantidad_recibida: i.cantidad_pendiente,
        lote: '',
    })) ?? [],
});
const procesando = ref(false);
const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });

const guardar = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post('/app/compras/recepcion', {
        orden_id: form.orden_id,
        remision_proveedor: form.remision_proveedor,
        factura_proveedor: form.factura_proveedor,
        transportista: form.transportista,
        observaciones: form.observaciones,
        items: form.items.filter(i => i.cantidad_recibida > 0).map(i => ({
            orden_item_id: i.orden_item_id,
            cantidad_recibida: i.cantidad_recibida,
            lote: i.lote || null,
        })),
    }, { onFinish: () => procesando.value = false });
};
</script>

<template>
    <Head title="Recibir mercancía"/>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-4">
            <Link href="/app/compras" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>

            <h1 class="text-2xl font-bold flex items-center gap-2">
                <Truck class="h-6 w-6 text-brand-600"/>
                Recibir mercancía
            </h1>

            <div v-if="!oc" class="card p-6 text-center text-surface-500">
                Necesitas seleccionar una OC. Ve a <Link href="/app/compras" class="text-brand-600 hover:underline">Compras</Link> y presiona "Recibir".
            </div>

            <div v-else class="space-y-4">
                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-1">OC de origen</div>
                    <div class="font-mono font-bold">{{ oc.numero }}</div>
                    <div class="text-sm text-surface-500">{{ oc.proveedor }}</div>
                </div>

                <div class="card p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div><label class="text-xs font-semibold">Remisión proveedor</label><input v-model="form.remision_proveedor" class="input w-full"/></div>
                    <div><label class="text-xs font-semibold">Factura proveedor</label><input v-model="form.factura_proveedor" class="input w-full"/></div>
                    <div><label class="text-xs font-semibold">Transportista</label><input v-model="form.transportista" class="input w-full"/></div>
                    <div class="md:col-span-3"><label class="text-xs font-semibold">Observaciones</label><textarea v-model="form.observaciones" rows="2" class="input w-full"></textarea></div>
                </div>

                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-2">Ítems a recibir ({{ form.items.length }})</div>
                    <table class="w-full text-sm">
                        <thead class="text-[10px] text-surface-500 uppercase border-b">
                            <tr>
                                <th class="text-left p-2">Descripción</th>
                                <th class="text-right p-2">Pendiente</th>
                                <th class="text-right p-2">A recibir</th>
                                <th class="text-left p-2">Lote</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(it, i) in form.items" :key="i">
                                <td class="p-2">{{ it.descripcion }}</td>
                                <td class="p-2 text-right">{{ it.cantidad_pendiente }}</td>
                                <td class="p-2"><input type="number" step="0.001" v-model.number="it.cantidad_recibida" :max="it.cantidad_pendiente" class="input w-24 text-right text-sm"/></td>
                                <td class="p-2"><input v-model="it.lote" class="input w-full text-sm" placeholder="Lote opcional"/></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button @click="guardar" :disabled="procesando" class="btn-primary">
                        <Save class="h-4 w-4"/> {{ procesando ? 'Guardando…' : 'Confirmar recepción' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
