<script setup>
import { reactive, ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Save, AlertTriangle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useEscClose } from '@/composables/useEscClose';
import { useMoney } from '@/composables/useMoney';
import { usePedidoBadge } from '@/composables/usePedidoBadge';

const props = defineProps({
    pedido: { type: Object, required: true },
    estadosDisponibles: { type: Array, required: true },
});

const { money } = useMoney();
const { badge } = usePedidoBadge();

const original = { ...props.pedido };
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

// U11 · dirty guard: bloquea salir sin guardar.
const sucio = computed(() => Object.keys(form).some(k => form[k] !== original[k]));

const beforeUnloadHandler = (e) => {
    if (sucio.value) {
        e.preventDefault();
        e.returnValue = '';
    }
};

// Re-audit UX#4 · dirty guard también sobre navegación Inertia (Link, router.visit).
// beforeunload solo cubre cerrar tab; router.on('before') cubre navegación SPA.
let removeInertiaGuard;
onMounted(() => {
    window.addEventListener('beforeunload', beforeUnloadHandler);
    removeInertiaGuard = router.on('before', (event) => {
        if (! sucio.value) return true;
        if (! window.confirm('Hay cambios sin guardar. ¿Salir sin guardar?')) {
            event.preventDefault();
            return false;
        }
        return true;
    });
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnloadHandler);
    removeInertiaGuard && removeInertiaGuard();
});

// U11 · confirmación cuando el estado nuevo es "riesgoso".
const ESTADOS_RIESGO = ['cancelado_dropi', 'cancelado_gb', 'devuelto', 'pagado'];
const cambioRiesgoso = computed(() =>
    form.estado !== original.estado && ESTADOS_RIESGO.includes(form.estado)
);

const modalConfirm = ref(false);
useEscClose(modalConfirm);
const solicitarGuardar = () => {
    if (cambioRiesgoso.value) {
        modalConfirm.value = true;
        return;
    }
    guardarReal();
};
const guardarReal = () => {
    if (procesando.value) return;
    procesando.value = true;
    modalConfirm.value = false;
    router.post(`/app/dropi/pedidos/${props.pedido.id}/actualizar`, { _method: 'put', ...form }, {
        preserveScroll: true,
        onSuccess: () => Object.assign(original, form),
        onFinish: () => procesando.value = false,
    });
};
</script>

<template>
    <Head :title="`Pedido ${pedido.guia}`"/>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-4">
            <Link href="/app/dropi" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="p-3 rounded-lg bg-red-500/15 border-l-4 border-red-500 text-red-700 dark:text-red-300 text-sm">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="sucio" class="p-2 rounded-lg bg-amber-500/15 border-l-4 border-amber-500 text-amber-700 dark:text-amber-300 text-xs flex items-center gap-2">
                <AlertTriangle class="h-3 w-3"/> Hay cambios sin guardar.
            </div>

            <div class="card p-5">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <div class="text-xs text-surface-500 dark:text-surface-400 uppercase">Pedido</div>
                        <h1 class="text-2xl font-bold font-mono">{{ pedido.guia }}</h1>
                        <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                            Dropi ID: {{ pedido.dropi_orden_id }} · Corte: {{ pedido.corte_numero ?? '—' }} ·
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="badge(original.estado).cls">
                                {{ badge(original.estado).label }}
                            </span>
                        </div>
                    </div>
                    <button @click="solicitarGuardar" :disabled="procesando || !sucio" class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                        <Save class="h-4 w-4"/> {{ procesando ? 'Guardando…' : 'Guardar' }}
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Estado</label>
                        <select v-model="form.estado" class="input w-full">
                            <option v-for="e in estadosDisponibles" :key="e.value" :value="e.value">{{ e.label }}</option>
                        </select>
                        <p v-if="cambioRiesgoso" class="text-[11px] mt-1 text-red-600 dark:text-red-400">
                            ⚠ Cambio a estado sensible — se pedirá confirmación al guardar.
                        </p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Cliente</label>
                        <input v-model="form.cliente_nombre" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Teléfono</label>
                        <input v-model="form.cliente_telefono" class="input w-full"/>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Dirección</label>
                        <input v-model="form.cliente_direccion" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Ciudad</label>
                        <input v-model="form.cliente_ciudad" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Departamento</label>
                        <input v-model="form.cliente_depto" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Transportadora</label>
                        <input v-model="form.transportadora" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Monto proveedor</label>
                        <input type="number" step="0.01" v-model.number="form.monto_esperado_proveedor" class="input w-full"/>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Ítems ({{ pedido.items.length }})</div>
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 dark:text-surface-400 uppercase">
                        <tr>
                            <th class="text-left p-2">SKU</th>
                            <th class="text-left p-2">Descripción</th>
                            <th class="text-right p-2">Cant.</th>
                            <th class="text-right p-2">Precio</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
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

        <!-- U11 · Modal confirmación de cambio riesgoso -->
        <div v-if="modalConfirm" @click.self="modalConfirm = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full border-l-4 border-red-500">
                <div class="flex items-start gap-3 mb-3">
                    <AlertTriangle class="h-6 w-6 text-red-500 flex-shrink-0"/>
                    <div>
                        <h3 class="text-lg font-bold">Cambio de estado sensible</h3>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                            Pasar el pedido <b class="font-mono">{{ pedido.guia }}</b>
                            de <b>{{ badge(original.estado).label }}</b> a <b>{{ badge(form.estado).label }}</b>
                            puede afectar contabilidad, inventario o cartera.
                        </p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <button @click="modalConfirm = false" class="btn-ghost">Cancelar</button>
                    <button @click="guardarReal" :disabled="procesando" class="btn-primary bg-red-600 hover:bg-red-700 disabled:opacity-50">
                        Confirmar y guardar
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
