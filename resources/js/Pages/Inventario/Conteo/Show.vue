<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, ArrowLeft, Play, Save, CheckCircle2, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useFecha } from '@/composables/useFecha';

const props = defineProps({ toma: { type: Object, required: true } });
const { fechaCorta } = useFecha();

const badge = (e) => ({
    borrador: 'bg-surface-200 text-surface-800',
    en_conteo: 'bg-amber-100 text-amber-800',
    ajustada: 'bg-emerald-100 text-emerald-800',
    anulada: 'bg-red-100 text-red-800',
}[e] || 'bg-surface-100');

const puedeIniciar = computed(() => props.toma.estado === 'borrador');
const puedeCapturar = computed(() => props.toma.estado === 'en_conteo');
const puedeCerrar = computed(() => props.toma.estado === 'en_conteo' && itemsContados.value > 0);

const busqueda = ref('');
const itemsFiltrados = computed(() => {
    const t = busqueda.value.trim().toLowerCase();
    if (!t) return props.toma.items;
    return props.toma.items.filter((it) =>
        (it.sku || '').toLowerCase().includes(t) ||
        (it.producto || '').toLowerCase().includes(t)
    );
});

const itemsContados = computed(() => props.toma.items.filter((it) => it.cantidad_contada !== null).length);
const progreso = computed(() => (props.toma.items.length ? Math.round((itemsContados.value / props.toma.items.length) * 100) : 0));

// Cantidades locales (double-binding)
const cantidades = reactive({});
props.toma.items.forEach((it) => (cantidades[it.id] = it.cantidad_contada));

// Guardado con debounce y estado por-item
const guardado = reactive({});
const guardando = reactive({});
let timers = {};
const guardarItem = (item) => {
    clearTimeout(timers[item.id]);
    guardando[item.id] = true;
    timers[item.id] = setTimeout(() => {
        router.post(
            `/app/inventario/conteos/${props.toma.id}/items/${item.id}`,
            { cantidad_contada: cantidades[item.id] === '' ? null : cantidades[item.id] },
            {
                preserveScroll: true,
                preserveState: true,
                only: [],
                onSuccess: () => {
                    guardado[item.id] = Date.now();
                    setTimeout(() => (guardado[item.id] = 0), 1200);
                },
                onFinish: () => { guardando[item.id] = false; },
            }
        );
    }, 350);
};

const iniciando = ref(false);
const iniciar = () => {
    if (!confirm('Iniciar conteo: se congelará el saldo actual del sistema como snapshot. ¿Continuar?')) return;
    iniciando.value = true;
    router.post(`/app/inventario/conteos/${props.toma.id}/iniciar`, {}, {
        preserveScroll: true,
        onFinish: () => (iniciando.value = false),
    });
};

const cerrando = ref(false);
const cerrar = () => {
    const dif = props.toma.items.filter((it) => it.cantidad_contada !== null && Number(it.diferencia) !== 0).length;
    const valor = props.toma.items
        .filter((it) => it.cantidad_contada !== null)
        .reduce((s, it) => s + Number(it.diferencia) * Number(it.costo_unit || 0), 0);
    const signo = valor >= 0 ? 'sobrante' : 'faltante';
    if (!confirm(`Cerrar conteo ${props.toma.numero}?\n\nItems con diferencia: ${dif}\nValor de ajuste: ${valor.toFixed(2)} (${signo})\n\nSe generarán movimientos en kardex y un asiento contable (1435 vs 4295/5299). Esta acción NO se puede deshacer.`)) return;
    cerrando.value = true;
    router.post(`/app/inventario/conteos/${props.toma.id}/cerrar`, {}, {
        preserveScroll: true,
        onFinish: () => (cerrando.value = false),
    });
};

// Escáner: input hidden capta código de barras (foco automático).
const scannerInput = ref(null);
const focoBarras = () => scannerInput.value?.focus();
const codigoScan = ref('');
const escaneoDetectado = (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const code = codigoScan.value.trim();
    codigoScan.value = '';
    if (!code) return;
    const item = props.toma.items.find((it) => it.sku === code);
    if (!item) { alert('SKU no encontrado en esta toma: ' + code); return; }
    // suma 1 al contado del item
    const actual = Number(cantidades[item.id] || 0);
    cantidades[item.id] = actual + 1;
    guardarItem(item);
    // scroll a la fila
    nextTick(() => {
        const row = document.getElementById('item-' + item.id);
        if (row) row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
};

onMounted(() => {
    if (puedeCapturar.value) focoBarras();
});
</script>

<template>
    <Head :title="`Conteo ${toma.numero}`" />
    <AppLayout>
        <div class="space-y-4">
            <Link href="/app/inventario/conteos" class="text-sm text-brand-600 inline-flex items-center gap-1 hover:underline">
                <ArrowLeft class="h-4 w-4" /> Volver a conteos
            </Link>

            <!-- Cabecera -->
            <div class="card p-5">
                <div class="flex items-start justify-between flex-wrap gap-3">
                    <div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-2xl font-bold flex items-center gap-2">
                                <ClipboardList class="h-6 w-6 text-brand-600" />
                                {{ toma.numero }}
                            </h1>
                            <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(toma.estado)]">{{ toma.estado }}</span>
                        </div>
                        <p class="text-sm text-surface-600 mt-1">
                            <span class="font-semibold">{{ toma.ubicacion }}</span> · {{ toma.tipo }} · {{ fechaCorta(toma.fecha_conteo) }}
                        </p>
                        <p class="text-xs text-surface-500">Creador: {{ toma.creador || '—' }}<template v-if="toma.cerrador"> · Cerrada por {{ toma.cerrador }}</template></p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <button v-if="puedeIniciar" @click="iniciar" :disabled="iniciando" class="btn-primary min-h-11">
                            <Play class="h-4 w-4" /> {{ iniciando ? 'Preparando…' : 'Iniciar conteo' }}
                        </button>
                        <button v-if="puedeCerrar" @click="cerrar" :disabled="cerrando" class="btn-primary min-h-11">
                            <CheckCircle2 class="h-4 w-4" /> {{ cerrando ? 'Cerrando…' : 'Cerrar y ajustar' }}
                        </button>
                    </div>
                </div>

                <!-- Progreso -->
                <div v-if="toma.items.length" class="mt-4">
                    <div class="flex justify-between text-xs mb-1">
                        <span>Progreso: {{ itemsContados }} / {{ toma.items.length }} items</span>
                        <span class="font-bold">{{ progreso }}%</span>
                    </div>
                    <div class="h-2 bg-surface-100 rounded overflow-hidden">
                        <div class="h-full bg-brand-600 transition-all" :style="{ width: progreso + '%' }"></div>
                    </div>
                </div>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="p-3 rounded-lg bg-red-500/15 border-l-4 border-red-500 text-red-700 text-sm">
                {{ $page.props.flash.error }}
            </div>

            <!-- Barra buscar + escáner -->
            <div v-if="puedeCapturar" class="card p-3 flex items-center gap-3 flex-wrap">
                <div class="relative flex-1 min-w-[240px]">
                    <input v-model="busqueda" class="input w-full pr-8" placeholder="Filtrar por SKU o nombre…" />
                    <Search class="absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400" />
                </div>
                <div class="flex items-center gap-2">
                    <input
                        ref="scannerInput"
                        v-model="codigoScan"
                        @keydown="escaneoDetectado"
                        class="input font-mono w-52"
                        placeholder="Escanear código de barras"
                        autocomplete="off"
                        inputmode="numeric"
                    />
                    <button @click="focoBarras" class="btn-ghost text-xs">Enfocar escáner</button>
                </div>
            </div>

            <!-- Tabla captura -->
            <div v-if="toma.items.length" class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">SKU</th>
                            <th class="text-left p-3">Producto</th>
                            <th class="text-right p-3">Sistema</th>
                            <th class="text-right p-3">Contado</th>
                            <th class="text-right p-3">Dif.</th>
                            <th class="text-center p-3 w-16">—</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="it in itemsFiltrados" :key="it.id" :id="`item-${it.id}`" class="hover:bg-surface-50">
                            <td class="p-3 font-mono text-xs">{{ it.sku }}</td>
                            <td class="p-3">
                                <div class="font-semibold">{{ it.producto }}</div>
                                <div class="text-xs text-surface-500">{{ it.detalle }}</div>
                            </td>
                            <td class="p-3 text-right font-mono">{{ it.saldo_sistema }}</td>
                            <td class="p-3 text-right">
                                <input v-if="puedeCapturar"
                                    type="number" step="1" min="0"
                                    v-model.number="cantidades[it.id]"
                                    @input="guardarItem(it)"
                                    class="input w-24 text-right font-mono font-bold text-lg min-h-11"
                                    inputmode="numeric"
                                    :placeholder="'—'"
                                />
                                <span v-else class="font-mono">{{ it.cantidad_contada === null ? '—' : it.cantidad_contada }}</span>
                            </td>
                            <td class="p-3 text-right font-bold" :class="Number(it.diferencia) === 0 ? 'text-surface-400' : (Number(it.diferencia) > 0 ? 'text-emerald-600' : 'text-red-600')">
                                {{ (cantidades[it.id] === null || cantidades[it.id] === '' || cantidades[it.id] === undefined) ? '—' : (Number(cantidades[it.id]) - Number(it.saldo_sistema)) }}
                            </td>
                            <td class="p-3 text-center">
                                <span v-if="guardando[it.id]" class="text-xs text-surface-400">…</span>
                                <span v-else-if="guardado[it.id]" class="text-xs text-emerald-600">✓</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else-if="puedeIniciar" class="card p-8 text-center text-surface-500">
                Sin items todavía. Haz clic en <b>Iniciar conteo</b> para congelar el saldo del sistema como snapshot.
            </div>
        </div>
    </AppLayout>
</template>
