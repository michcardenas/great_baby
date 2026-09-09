<script setup>
import { reactive, ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Truck, Save, ArrowLeft, AlertTriangle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useMoney } from '@/composables/useMoney';

const props = defineProps({ oc: { type: Object, default: null } });

const { money } = useMoney();

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
        precio_unit: i.precio_unit,
        lote: '',
    })) ?? [],
});
const procesando = ref(false);

// Re-audit M2 UX-A7 · clamp reactivo — antes `:max` era solo hint HTML, el
// usuario podía escribir 30 sobre pendiente 20 y romper el saldo.
const clampCantidad = (it) => {
    const n = Number(it.cantidad_recibida);
    if (isNaN(n) || n < 0) it.cantidad_recibida = 0;
    else if (n > it.cantidad_pendiente) it.cantidad_recibida = it.cantidad_pendiente;
};

// Re-audit M2 UX-M12 · deshabilitar submit si ningún ítem tiene cantidad > 0.
const itemsAConfirmar = computed(() => form.items.filter(i => Number(i.cantidad_recibida) > 0));
const totalRecepcion = computed(() =>
    itemsAConfirmar.value.reduce((s, i) => s + (Number(i.cantidad_recibida) * Number(i.precio_unit || 0)), 0));

const guardar = () => {
    if (procesando.value || itemsAConfirmar.value.length === 0) return;
    procesando.value = true;
    router.post('/app/compras/recepcion', {
        orden_id: form.orden_id,
        remision_proveedor: form.remision_proveedor,
        factura_proveedor: form.factura_proveedor,
        transportista: form.transportista,
        observaciones: form.observaciones,
        items: itemsAConfirmar.value.map(i => ({
            orden_item_id: i.orden_item_id,
            cantidad_recibida: i.cantidad_recibida,
            lote: i.lote || null,
        })),
    }, {
        preserveScroll: true,
        onFinish: () => procesando.value = false,
    });
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

            <div v-if="$page.props.errors && Object.keys($page.props.errors).length" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/40 border-l-4 border-red-500 text-red-700 dark:text-red-300 text-sm space-y-1">
                <div v-for="(msg, k) in $page.props.errors" :key="k">{{ msg }}</div>
            </div>

            <div v-if="!oc" class="card p-6 text-center text-surface-500">
                Necesitas seleccionar una OC. Ve a <Link href="/app/compras" class="text-brand-600 hover:underline">Compras</Link> y presiona "Recibir" en la OC aprobada.
            </div>

            <div v-else class="space-y-4">
                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-1">OC de origen</div>
                    <div class="font-mono font-bold">{{ oc.numero }}</div>
                    <div class="text-sm text-surface-500">{{ oc.proveedor }}</div>
                </div>

                <div class="card p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div><label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Remisión proveedor</label><input v-model="form.remision_proveedor" class="input w-full" maxlength="100"/></div>
                    <div><label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Factura proveedor</label><input v-model="form.factura_proveedor" class="input w-full" maxlength="100"/></div>
                    <div><label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Transportista</label><input v-model="form.transportista" class="input w-full" maxlength="100"/></div>
                    <div class="md:col-span-3"><label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Observaciones</label><textarea v-model="form.observaciones" rows="2" class="input w-full" maxlength="1000"></textarea></div>
                </div>

                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-2">Ítems a recibir ({{ form.items.length }})</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-[10px] text-surface-500 uppercase border-b border-surface-200 dark:border-surface-800">
                                <tr>
                                    <th class="text-left p-2">Descripción</th>
                                    <th class="text-right p-2">Pendiente</th>
                                    <th class="text-right p-2">A recibir</th>
                                    <th class="text-right p-2">Subtotal línea</th>
                                    <th class="text-left p-2">Lote</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                                <tr v-for="(it, i) in form.items" :key="i">
                                    <td class="p-2" :title="it.descripcion">{{ it.descripcion }}</td>
                                    <td class="p-2 text-right font-mono">{{ it.cantidad_pendiente }}</td>
                                    <td class="p-2">
                                        <input type="number" step="0.001" min="0" :max="it.cantidad_pendiente"
                                               v-model.number="it.cantidad_recibida"
                                               @change="clampCantidad(it)"
                                               @blur="clampCantidad(it)"
                                               class="input w-24 text-right text-sm"/>
                                    </td>
                                    <td class="p-2 text-right font-mono text-xs">{{ money((it.cantidad_recibida || 0) * (it.precio_unit || 0)) }}</td>
                                    <td class="p-2"><input v-model="it.lote" class="input w-full text-sm" placeholder="Opcional" maxlength="80"/></td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t-2 border-surface-300 dark:border-surface-700 font-bold">
                                <tr>
                                    <td colspan="3" class="p-2 text-right">Total recepción</td>
                                    <td class="p-2 text-right font-mono">{{ money(totalRecepcion) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div v-if="itemsAConfirmar.length === 0" class="mt-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border-l-4 border-amber-500 text-amber-800 dark:text-amber-200 text-xs flex items-start gap-2">
                        <AlertTriangle class="h-4 w-4 flex-shrink-0 mt-0.5"/>
                        <div>Marca al menos un ítem con cantidad mayor a 0 antes de confirmar.</div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button @click="guardar" :disabled="procesando || itemsAConfirmar.length === 0" class="btn-primary disabled:opacity-50">
                        <Save class="h-4 w-4"/> {{ procesando ? 'Guardando…' : 'Confirmar recepción' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
