<script setup>
// INV-B1 · Buscador inteligente para Inventario/Logística.
// Muestra en una sola caja resultados agrupados: variantes, productos
// agregados y ubicaciones. Cada fila lleva al kardex o al reporte de stock.
// Atajos: `/` enfoca · Esc cierra · ↑↓ navega · Enter abre resultado.
import { ref, onMounted, onBeforeUnmount, nextTick, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Search, Package, Package2, Warehouse, ArrowRight, X } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    placeholder: { type: String, default: 'Buscar SKU, producto, referencia o bodega… ( / )' },
    autofocus: { type: Boolean, default: false },
});

const q = ref('');
const abierto = ref(false);
const cargando = ref(false);
const resultados = ref({ variantes: [], productos: [], ubicaciones: [], total: 0 });
const inputRef = ref(null);
const seleccionado = ref(0);
let timer = null;

const planos = computed(() => [
    ...resultados.value.variantes.map(x => ({ ...x, _grupo: 'variante' })),
    ...resultados.value.productos.map(x => ({ ...x, _grupo: 'producto_agregado' })),
    ...resultados.value.ubicaciones.map(x => ({ ...x, _grupo: 'ubicacion' })),
]);

const buscar = async () => {
    if (q.value.trim().length < 2) {
        resultados.value = { variantes: [], productos: [], ubicaciones: [], total: 0 };
        return;
    }
    cargando.value = true;
    try {
        const { data } = await axios.get('/app/inventario/buscador', { params: { q: q.value } });
        resultados.value = data;
        seleccionado.value = 0;
    } finally {
        cargando.value = false;
    }
};

const onInput = () => {
    clearTimeout(timer);
    abierto.value = true;
    timer = setTimeout(buscar, 220);
};

const limpiar = () => {
    q.value = '';
    resultados.value = { variantes: [], productos: [], ubicaciones: [], total: 0 };
    inputRef.value?.focus();
};

const abrir = (r) => {
    const url = r.url_kardex || r.url_reporte;
    if (!url) return;
    router.visit(url);
    abierto.value = false;
    q.value = '';
};

const onKey = (e) => {
    if (!abierto.value) return;
    const total = planos.value.length;
    if (e.key === 'ArrowDown') { e.preventDefault(); seleccionado.value = Math.min(seleccionado.value + 1, total - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); seleccionado.value = Math.max(seleccionado.value - 1, 0); }
    else if (e.key === 'Enter' && total) { e.preventDefault(); abrir(planos.value[seleccionado.value]); }
    else if (e.key === 'Escape') { abierto.value = false; }
};

const atajoGlobal = (e) => {
    // FIX-S0 · no robar foco si estás tipeando en un input/textarea/select o
    // en un contenteditable (WYSIWYG). Antes solo excluía INPUT y TEXTAREA.
    const ae = document.activeElement;
    const esFormulario = ae && (
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(ae.tagName) || ae.isContentEditable
    );
    const enModal = !!document.querySelector('[role="dialog"][open], .fixed.inset-0.z-50');
    if (e.key === '/' && !esFormulario && !enModal) {
        e.preventDefault();
        inputRef.value?.focus();
        abierto.value = true;
    }
};

onMounted(() => {
    window.addEventListener('keydown', atajoGlobal);
    if (props.autofocus) nextTick(() => inputRef.value?.focus());
});
onBeforeUnmount(() => window.removeEventListener('keydown', atajoGlobal));
</script>

<template>
    <div class="relative">
        <div class="relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400"/>
            <input ref="inputRef" v-model="q" @input="onInput" @focus="abierto = true" @keydown="onKey"
                   :placeholder="placeholder"
                   class="input w-full pl-9 pr-9"
                   autocomplete="off"/>
            <button v-if="q" @click="limpiar" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-surface-100">
                <X class="h-3.5 w-3.5 text-surface-400"/>
            </button>
        </div>

        <div v-if="abierto && q.length >= 2" class="absolute z-30 mt-1 w-full max-h-[480px] overflow-y-auto rounded-xl border bg-white shadow-2xl">
            <div v-if="cargando" class="p-3 text-xs text-surface-500">Buscando…</div>
            <!-- FIX-S0 · banner si el user tiene scope restringido pero sin bodegas asignadas -->
            <div v-else-if="resultados.scope_vacio" class="p-3 bg-amber-50 border-b border-amber-200 text-xs text-amber-800">
                Tu usuario no tiene bodega asignada aún. Los resultados aparecen pero el stock no es confiable · pedile al admin que te asigne una ubicación.
            </div>
            <div v-if="!cargando && !resultados.total" class="p-6 text-center text-sm text-surface-500">
                Sin resultados para <strong>{{ q }}</strong>.
            </div>
            <template v-if="!cargando && resultados.total">
                <!-- Variantes -->
                <div v-if="resultados.variantes.length">
                    <div class="px-3 py-1.5 text-[10px] font-bold uppercase text-brand-700 bg-brand-50 border-b border-brand-100 flex items-center gap-1.5">
                        <Package class="h-3 w-3"/> Variantes · {{ resultados.variantes.length }}
                    </div>
                    <button v-for="(v, i) in resultados.variantes" :key="`v${v.id}`" @click="abrir(v)"
                            @mouseenter="seleccionado = i"
                            :class="['w-full text-left px-3 py-2 text-xs border-b flex items-center gap-2 hover:bg-brand-50/50',
                                     seleccionado === i && 'bg-brand-50']">
                        <div class="flex-1">
                            <div class="font-semibold text-surface-800">{{ v.producto || '?' }} <span class="text-surface-400 font-normal">· {{ v.detalle || '—' }}</span></div>
                            <div class="font-mono text-[11px] text-sky-700">{{ v.sku }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] uppercase text-surface-400">stock</div>
                            <div v-if="v.stock === null" class="font-bold text-surface-300" title="Sin bodega asignada">?</div>
                            <div v-else :class="['font-bold', v.stock > 0 ? 'text-emerald-700' : 'text-rose-500']">{{ v.stock }}</div>
                        </div>
                        <ArrowRight class="h-3.5 w-3.5 text-surface-300"/>
                    </button>
                </div>

                <!-- Productos agregados -->
                <div v-if="resultados.productos.length">
                    <div class="px-3 py-1.5 text-[10px] font-bold uppercase text-amber-700 bg-amber-50 border-b border-amber-100 flex items-center gap-1.5">
                        <Package2 class="h-3 w-3"/> Productos agregados (sin desglose) · {{ resultados.productos.length }}
                    </div>
                    <button v-for="(p, i) in resultados.productos" :key="`p${p.id}`" @click="abrir(p)"
                            @mouseenter="seleccionado = resultados.variantes.length + i"
                            :class="['w-full text-left px-3 py-2 text-xs border-b flex items-center gap-2 hover:bg-amber-50/50',
                                     seleccionado === (resultados.variantes.length + i) && 'bg-amber-50']">
                        <div class="flex-1">
                            <div class="font-semibold text-surface-800">{{ p.nombre }}</div>
                            <div class="font-mono text-[11px] text-amber-700">{{ p.referencia }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] uppercase text-surface-400">stock</div>
                            <div v-if="p.stock === null" class="font-bold text-surface-300" title="Sin bodega asignada">?</div>
                            <div v-else :class="['font-bold', p.stock > 0 ? 'text-emerald-700' : 'text-rose-500']">{{ p.stock }}</div>
                        </div>
                        <ArrowRight class="h-3.5 w-3.5 text-surface-300"/>
                    </button>
                </div>

                <!-- Ubicaciones -->
                <div v-if="resultados.ubicaciones.length">
                    <div class="px-3 py-1.5 text-[10px] font-bold uppercase text-violet-700 bg-violet-50 border-b border-violet-100 flex items-center gap-1.5">
                        <Warehouse class="h-3 w-3"/> Ubicaciones · {{ resultados.ubicaciones.length }}
                    </div>
                    <button v-for="(u, i) in resultados.ubicaciones" :key="`u${u.id}`" @click="abrir(u)"
                            @mouseenter="seleccionado = resultados.variantes.length + resultados.productos.length + i"
                            :class="['w-full text-left px-3 py-2 text-xs border-b flex items-center gap-2 hover:bg-violet-50/50',
                                     seleccionado === (resultados.variantes.length + resultados.productos.length + i) && 'bg-violet-50']">
                        <div class="flex-1">
                            <div class="font-semibold text-surface-800 flex items-center gap-1.5">
                                <span class="font-mono text-violet-700">{{ u.codigo }}</span>
                                <span>· {{ u.nombre }}</span>
                                <span v-if="!u.activa" class="text-[10px] uppercase bg-surface-200 text-surface-600 px-1 rounded">inactiva</span>
                            </div>
                            <div class="text-[11px] text-surface-500">
                                <span v-if="u.ciudad">{{ u.ciudad }} · </span>
                                <span class="capitalize">{{ u.categoria }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] uppercase text-surface-400">SKUs</div>
                            <div class="font-bold text-violet-700">{{ u.skus_distintos }}</div>
                        </div>
                        <ArrowRight class="h-3.5 w-3.5 text-surface-300"/>
                    </button>
                </div>

                <div class="px-3 py-1.5 text-[10px] text-surface-400 bg-surface-50 border-t flex items-center justify-between">
                    <span>↑↓ navegar · ↵ abrir · esc cerrar</span>
                    <span>{{ resultados.total }} resultados</span>
                </div>
            </template>
        </div>

        <!-- Backdrop clic cierra -->
        <div v-if="abierto && q.length >= 2" @click="abierto = false" class="fixed inset-0 z-20"></div>
    </div>
</template>
