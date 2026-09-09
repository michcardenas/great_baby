<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftRight, ArrowLeft, Plus, Trash2, Send, PackageCheck, Ban, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useEscClose } from '@/composables/useEscClose';
import { useFecha } from '@/composables/useFecha';

const props = defineProps({ traslado: { type: Object, required: true } });
const { fechaCorta } = useFecha();

const badge = (e) => ({
    borrador: 'bg-surface-200 text-surface-800',
    en_transito: 'bg-amber-100 text-amber-800',
    recibido: 'bg-emerald-100 text-emerald-800',
    anulado: 'bg-red-100 text-red-800',
}[e] || 'bg-surface-100');

const puedeEditar = computed(() => props.traslado.estado === 'borrador');
const puedeEnviar = computed(() => props.traslado.estado === 'borrador' && props.traslado.items.length > 0);
const puedeRecibir = computed(() => props.traslado.estado === 'en_transito');
const puedeAnular = computed(() => ['borrador', 'en_transito', 'recibido'].includes(props.traslado.estado));

// Repeater items
const busqueda = ref('');
const resultados = ref([]);
const buscando = ref(false);
const nuevoItem = reactive({ variante_id: null, sku: '', producto: '', detalle: '', cantidad: null, notas: '' });

let debTimer;
const buscar = () => {
    clearTimeout(debTimer);
    if (busqueda.value.length < 2) { resultados.value = []; return; }
    debTimer = setTimeout(async () => {
        buscando.value = true;
        try {
            const r = await fetch(`/app/inventario/buscar-variantes?q=${encodeURIComponent(busqueda.value)}`);
            resultados.value = await r.json();
        } finally { buscando.value = false; }
    }, 200);
};

const seleccionarVariante = (v) => {
    nuevoItem.variante_id = v.id;
    nuevoItem.sku = v.sku;
    nuevoItem.producto = v.producto;
    nuevoItem.detalle = v.detalle;
    busqueda.value = v.label;
    resultados.value = [];
};

const procesandoItem = ref(false);
const guardarItem = () => {
    if (procesandoItem.value || !nuevoItem.variante_id || !nuevoItem.cantidad) return;
    procesandoItem.value = true;
    router.post(`/app/inventario/traslados/${props.traslado.id}/items`, {
        variante_id: nuevoItem.variante_id,
        cantidad: nuevoItem.cantidad,
        notas: nuevoItem.notas || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            Object.assign(nuevoItem, { variante_id: null, sku: '', producto: '', detalle: '', cantidad: null, notas: '' });
            busqueda.value = '';
        },
        onFinish: () => (procesandoItem.value = false),
    });
};

const eliminarItem = (it) => {
    if (!confirm(`Eliminar ${it.producto} (${it.sku}) del traslado?`)) return;
    router.delete(`/app/inventario/traslados/${props.traslado.id}/items/${it.id}`, { preserveScroll: true });
};

// State-machine actions
const enviando = ref(false);
const enviar = () => {
    const total = props.traslado.items.reduce((s, it) => s + Number(it.cantidad_solicitada || 0), 0);
    if (!confirm(`Enviar traslado ${props.traslado.numero}?\n\nSe descontarán ${total} unidades del origen (${props.traslado.origen.nombre}) y quedarán En Tránsito hacia ${props.traslado.destino.nombre}. Esta acción NO se puede deshacer sin una reversa.`)) return;
    enviando.value = true;
    router.post(`/app/inventario/traslados/${props.traslado.id}/enviar`, {}, { preserveScroll: true, onFinish: () => (enviando.value = false) });
};

const recibiendo = ref(false);
const recibir = () => {
    const total = props.traslado.items.reduce((s, it) => s + Number(it.cantidad_solicitada || 0), 0);
    if (!confirm(`Confirmar recepción de traslado ${props.traslado.numero}?\n\nSe ingresarán ${total} unidades en el destino (${props.traslado.destino.nombre}).`)) return;
    recibiendo.value = true;
    router.post(`/app/inventario/traslados/${props.traslado.id}/recibir`, {}, { preserveScroll: true, onFinish: () => (recibiendo.value = false) });
};

const modalAnular = ref(false);
useEscClose(modalAnular);
const motivoAnulacion = ref('');
const anulando = ref(false);
const anular = () => {
    if (motivoAnulacion.value.trim().length < 5) return;
    anulando.value = true;
    router.post(`/app/inventario/traslados/${props.traslado.id}/anular`, { motivo: motivoAnulacion.value }, {
        preserveScroll: true,
        onSuccess: () => { modalAnular.value = false; motivoAnulacion.value = ''; },
        onFinish: () => (anulando.value = false),
    });
};
</script>

<template>
    <Head :title="`Traslado ${traslado.numero}`" />
    <AppLayout>
        <div class="space-y-4">
            <Link href="/app/inventario/traslados" class="text-sm text-brand-600 inline-flex items-center gap-1 hover:underline">
                <ArrowLeft class="h-4 w-4" /> Volver a traslados
            </Link>

            <!-- Cabecera -->
            <div class="card p-5">
                <div class="flex items-start justify-between flex-wrap gap-3">
                    <div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-2xl font-bold flex items-center gap-2">
                                <ArrowLeftRight class="h-6 w-6 text-brand-600" />
                                {{ traslado.numero }}
                            </h1>
                            <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(traslado.estado)]">
                                {{ traslado.estado }}
                            </span>
                        </div>
                        <p class="text-sm text-surface-600 mt-1">
                            <span class="font-semibold">{{ traslado.origen.nombre }}</span>
                            <ArrowLeftRight class="inline h-4 w-4 mx-2" />
                            <span class="font-semibold">{{ traslado.destino.nombre }}</span>
                        </p>
                        <p class="text-xs text-surface-500 mt-1">
                            Solicitado por {{ traslado.solicitante || '—' }} · {{ fechaCorta(traslado.fecha_solicitud) }}
                            <template v-if="traslado.fecha_envio"> · Enviado {{ traslado.fecha_envio }}</template>
                            <template v-if="traslado.fecha_ejecucion"> · Recibido {{ traslado.fecha_ejecucion }}</template>
                        </p>
                        <p v-if="traslado.motivo" class="text-xs text-surface-500 mt-1">Motivo: {{ traslado.motivo }}</p>
                        <p v-if="traslado.observaciones" class="text-xs text-surface-500 mt-1 whitespace-pre-wrap">{{ traslado.observaciones }}</p>
                    </div>
                    <!-- Botones state-machine -->
                    <div class="flex gap-2 flex-wrap">
                        <button v-if="puedeEnviar" @click="enviar" :disabled="enviando" class="btn-primary min-h-11">
                            <Send class="h-4 w-4" /> {{ enviando ? 'Enviando…' : 'Enviar' }}
                        </button>
                        <button v-if="puedeRecibir" @click="recibir" :disabled="recibiendo" class="btn-primary min-h-11">
                            <PackageCheck class="h-4 w-4" /> {{ recibiendo ? 'Recibiendo…' : 'Confirmar recepción' }}
                        </button>
                        <button v-if="puedeAnular" @click="modalAnular = true" class="btn-danger min-h-11">
                            <Ban class="h-4 w-4" /> Anular
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="p-3 rounded-lg bg-red-500/15 border-l-4 border-red-500 text-red-700 text-sm">
                {{ $page.props.flash.error }}
            </div>

            <!-- Agregar ítem (solo Borrador) -->
            <div v-if="puedeEditar" class="card p-4">
                <h3 class="text-lg font-bold mb-3">Agregar producto</h3>
                <div class="grid gap-3 md:grid-cols-[2fr,1fr,1fr,auto]">
                    <div class="relative">
                        <label class="text-xs font-semibold">Buscar producto o SKU</label>
                        <div class="relative">
                            <input v-model="busqueda" @input="buscar" class="input w-full pr-8" placeholder="Nombre, referencia o código de barras"
                                autocomplete="off" inputmode="text" />
                            <Search class="absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400" />
                        </div>
                        <ul v-if="resultados.length" class="absolute z-10 mt-1 w-full max-h-60 overflow-auto border rounded-lg bg-white shadow-lg">
                            <li v-for="r in resultados" :key="r.id" @click="seleccionarVariante(r)"
                                class="p-2 text-xs cursor-pointer hover:bg-brand-50 border-b last:border-0">
                                <div class="font-semibold">{{ r.producto }}</div>
                                <div class="text-surface-500">{{ r.detalle }} · <span class="font-mono">{{ r.sku }}</span></div>
                            </li>
                        </ul>
                        <p v-if="busqueda.length >= 2 && !resultados.length && !buscando" class="text-xs text-surface-400 mt-1">Sin resultados.</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Cantidad</label>
                        <input type="number" step="0.01" min="0.01" v-model.number="nuevoItem.cantidad" class="input w-full" inputmode="decimal" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Notas</label>
                        <input v-model="nuevoItem.notas" class="input w-full" placeholder="Opcional" />
                    </div>
                    <div class="flex items-end">
                        <button @click="guardarItem" :disabled="procesandoItem || !nuevoItem.variante_id || !nuevoItem.cantidad" class="btn-primary min-h-11">
                            <Plus class="h-4 w-4" /> Agregar
                        </button>
                    </div>
                </div>
                <p v-if="nuevoItem.variante_id" class="text-xs text-emerald-700 mt-2">
                    Seleccionado: <b>{{ nuevoItem.producto }}</b> · {{ nuevoItem.detalle }} · <span class="font-mono">{{ nuevoItem.sku }}</span>
                </p>
            </div>

            <!-- Tabla items -->
            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">SKU</th>
                            <th class="text-left p-3">Producto</th>
                            <th class="text-left p-3">Detalle</th>
                            <th class="text-right p-3">Cantidad</th>
                            <th class="text-right p-3">Ejecutada</th>
                            <th class="text-left p-3">Notas</th>
                            <th v-if="puedeEditar" class="text-center p-3">—</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!traslado.items.length"><td :colspan="puedeEditar ? 7 : 6" class="p-6 text-center text-surface-500">Sin ítems. Agrega productos arriba para poder enviar el traslado.</td></tr>
                        <tr v-for="it in traslado.items" :key="it.id" class="hover:bg-surface-50">
                            <td class="p-3 font-mono text-xs">{{ it.sku }}</td>
                            <td class="p-3">{{ it.producto }}</td>
                            <td class="p-3 text-xs text-surface-500">{{ it.detalle }}</td>
                            <td class="p-3 text-right font-bold">{{ it.cantidad_solicitada }}</td>
                            <td class="p-3 text-right" :class="Number(it.cantidad_ejecutada) > 0 ? 'text-emerald-600 font-bold' : 'text-surface-400'">
                                {{ it.cantidad_ejecutada }}
                            </td>
                            <td class="p-3 text-xs">{{ it.notas || '—' }}</td>
                            <td v-if="puedeEditar" class="p-3 text-center">
                                <button @click="eliminarItem(it)" class="text-red-600 hover:bg-red-50 p-1 rounded">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="traslado.items.length" class="bg-surface-50 font-bold">
                            <td class="p-3" colspan="3">Total ítems</td>
                            <td class="p-3 text-right">{{ traslado.items.reduce((s, i) => s + Number(i.cantidad_solicitada || 0), 0) }}</td>
                            <td class="p-3 text-right">{{ traslado.items.reduce((s, i) => s + Number(i.cantidad_ejecutada || 0), 0) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal anular -->
        <div v-if="modalAnular" @click.self="modalAnular = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-2 text-red-700">Anular traslado {{ traslado.numero }}</h3>
                <p class="text-xs text-surface-600 mb-3">
                    Si el traslado ya fue enviado, se generará una reversa automática que devolverá el stock al origen (y lo retirará del destino, si ya recibió). El motivo queda registrado para auditoría DIAN.
                </p>
                <label class="text-xs font-semibold">Motivo (mínimo 5 caracteres)</label>
                <textarea v-model="motivoAnulacion" rows="3" class="input w-full" placeholder="Ej: error de digitación en cantidades"></textarea>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modalAnular = false" class="btn-ghost min-h-11">Cancelar</button>
                    <button @click="anular" :disabled="anulando || motivoAnulacion.trim().length < 5" class="btn-danger min-h-11">
                        {{ anulando ? 'Anulando…' : 'Anular traslado' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
