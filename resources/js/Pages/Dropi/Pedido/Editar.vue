<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Save, Package } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    pedido: { type: Object, required: true },
    estadosDisponibles: { type: Array, required: true },
});

const form = reactive({
    estado: props.pedido.estado,
    cliente_nombre: props.pedido.cliente_nombre,
    cliente_telefono: props.pedido.cliente_telefono,
    cliente_direccion: props.pedido.cliente_direccion,
    cliente_ciudad: props.pedido.cliente_ciudad,
    cliente_depto: props.pedido.cliente_depto,
    transportadora: props.pedido.transportadora,
    monto_esperado_proveedor: props.pedido.monto_esperado_proveedor,
});
const procesando = ref(false);

const guardar = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post(`/app/dropi/pedidos/${props.pedido.id}/actualizar`, { _method: 'put', ...form }, {
        preserveScroll: true, onFinish: () => procesando.value = false,
    });
};
const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
</script>

<template>
    <Head :title="`Pedido ${pedido.guia}`"/>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-4">
            <Link href="/app/dropi" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div class="card p-5">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <div class="text-xs text-surface-500 uppercase">Pedido</div>
                        <h1 class="text-2xl font-bold font-mono">{{ pedido.guia }}</h1>
                        <div class="text-xs text-surface-500 mt-1">Dropi ID: {{ pedido.dropi_orden_id }} · Corte: {{ pedido.corte_numero ?? '—' }}</div>
                    </div>
                    <button @click="guardar" :disabled="procesando" class="btn-primary">
                        <Save class="h-4 w-4"/> {{ procesando ? 'Guardando…' : 'Guardar' }}
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold text-surface-600">Estado</label>
                        <select v-model="form.estado" class="input w-full">
                            <option v-for="e in estadosDisponibles" :key="e.value" :value="e.value">{{ e.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Cliente</label>
                        <input v-model="form.cliente_nombre" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Teléfono</label>
                        <input v-model="form.cliente_telefono" class="input w-full"/>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold text-surface-600">Dirección</label>
                        <input v-model="form.cliente_direccion" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Ciudad</label>
                        <input v-model="form.cliente_ciudad" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Departamento</label>
                        <input v-model="form.cliente_depto" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Transportadora</label>
                        <input v-model="form.transportadora" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Monto proveedor</label>
                        <input type="number" step="0.01" v-model.number="form.monto_esperado_proveedor" class="input w-full"/>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Ítems ({{ pedido.items.length }})</div>
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase">
                        <tr>
                            <th class="text-left p-2">SKU</th>
                            <th class="text-left p-2">Descripción</th>
                            <th class="text-right p-2">Cant.</th>
                            <th class="text-right p-2">Precio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="(it, idx) in pedido.items" :key="idx">
                            <td class="p-2 font-mono text-xs">{{ it.sku }}</td>
                            <td class="p-2">{{ it.descripcion }}</td>
                            <td class="p-2 text-right font-bold">{{ it.cantidad }}</td>
                            <td class="p-2 text-right">{{ money(it.precio) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
