<script setup>
import { ref, reactive, watch, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    Package, ArrowLeft, Save, Info, DollarSign, Ruler, FileText,
    Cloud, CloudOff, Trash2, Plus, X, Search,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    producto: { type: Object, default: null },
    catalogos: { type: Object, required: true },
});

const esNuevo = !props.producto;
const tab = ref('general');

// Estructura completa del form.
const form = useForm({
    referencia: props.producto?.referencia || '',
    nombre: props.producto?.nombre || '',
    descripcion: props.producto?.descripcion || '',
    descripcion_ampliada: props.producto?.descripcion_ampliada || '',
    ficha_tecnica: props.producto?.ficha_tecnica || '',
    activo: props.producto?.activo ?? true,
    // Pestaña 1: General
    marca_id: props.producto?.marca_id || null,
    categoria_id: props.producto?.categoria_id || null,
    coleccion_id: props.producto?.coleccion_id || null,
    linea_id: props.producto?.linea_id || null,
    grupo_id: props.producto?.grupo_id || null,
    subgrupo_id: props.producto?.subgrupo_id || null,
    clase_id: props.producto?.clase_id || null,
    unidad_medida_id: props.producto?.unidad_medida_id || null,
    unidad_compra_id: props.producto?.unidad_compra_id || null,
    factor_conversion: props.producto?.factor_conversion || null,
    posicion_arancelaria: props.producto?.posicion_arancelaria || '',
    reposicion_max_dias: props.producto?.reposicion_max_dias || null,
    proteger_precio: props.producto?.proteger_precio ?? false,
    maneja_lotes: props.producto?.maneja_lotes ?? false,
    maneja_seriales: props.producto?.maneja_seriales ?? false,
    es_estadistico: props.producto?.es_estadistico ?? false,
    requiere_talla: props.producto?.requiere_talla ?? false,
    es_set: props.producto?.es_set ?? false,
    neto: props.producto?.neto ?? true,
    desglose_stock: props.producto?.desglose_stock ?? true,
    stock_directo: props.producto?.stock_directo || 0,
    // Pestaña 2: Comercial
    impuesto_id: props.producto?.impuesto_id || null,
    precio_proveedor: props.producto?.precio_proveedor || 0,
    rentabilidad_pct: props.producto?.rentabilidad_pct || null,
    descuento_default_pct: props.producto?.descuento_default_pct || null,
    valor_gasto_venta_niif: props.producto?.valor_gasto_venta_niif || null,
    valor_neto_realizable_niif: props.producto?.valor_neto_realizable_niif || null,
    cta_ingreso: props.producto?.cta_ingreso || '',
    cta_iva_venta: props.producto?.cta_iva_venta || '',
    cta_costo: props.producto?.cta_costo || '',
    cta_inventario: props.producto?.cta_inventario || '',
    cta_devolucion: props.producto?.cta_devolucion || '',
    cta_descuento: props.producto?.cta_descuento || '',
    centro_costo: props.producto?.centro_costo || '',
    notas_contables: props.producto?.notas_contables || '',
    // Pestaña 3: Características
    peso_gr: props.producto?.peso_gr || null,
    alto_cm: props.producto?.alto_cm || null,
    ancho_cm: props.producto?.ancho_cm || null,
    largo_cm: props.producto?.largo_cm || null,
    // M:M
    accesorios: props.producto?.accesorios || [],
    sustitutos: props.producto?.sustitutos || [],
});

// QA-FIX #14 · Cascada Línea → Grupo → Subgrupo → Clase con filtros LOCALES
// (todo el árbol viene precargado desde el controller — ver catalogosParaForm).
// Antes: 3 fetch secuenciales por selección (~2s cumulativo). Ahora: 0 round-trips.
const grupos = computed(() =>
    form.linea_id ? (props.catalogos.grupos || []).filter(g => g.linea_id === form.linea_id) : []
);
const subgrupos = computed(() =>
    form.grupo_id ? (props.catalogos.subgrupos || []).filter(s => s.grupo_id === form.grupo_id) : []
);
const clases = computed(() =>
    form.subgrupo_id ? (props.catalogos.clases || []).filter(c => c.subgrupo_id === form.subgrupo_id) : []
);

// Solo reset de hijos si el usuario cambia el padre (no en hidratación).
let hidratando = ! esNuevo;
watch(() => form.linea_id, () => { if (!hidratando) { form.grupo_id = null; form.subgrupo_id = null; form.clase_id = null; } });
watch(() => form.grupo_id, () => { if (!hidratando) { form.subgrupo_id = null; form.clase_id = null; } });
watch(() => form.subgrupo_id, () => { if (!hidratando) { form.clase_id = null; } });
// Después del primer tick, aceptar cambios como acciones del usuario.
if (hidratando) setTimeout(() => { hidratando = false; }, 100);

// Buscador de productos para accesorios/sustitutos
const buscar = ref('');
const resultados = ref([]);
const buscando = ref(false);
let debSearch;
watch(buscar, () => {
    clearTimeout(debSearch);
    if (buscar.value.length < 2) { resultados.value = []; return; }
    debSearch = setTimeout(async () => {
        buscando.value = true;
        try {
            const r = await fetch(`/app/catalogo/productos-buscar?q=${encodeURIComponent(buscar.value)}`, { credentials: 'same-origin' });
            if (r.ok) resultados.value = await r.json();
        } finally { buscando.value = false; }
    }, 300);
});

const agregarAccesorio = (p) => {
    if (form.accesorios.find(a => a.id === p.id)) return;
    form.accesorios.push({ id: p.id, referencia: p.referencia, nombre: p.nombre, cantidad: 1 });
    buscar.value = '';
};
const quitarAccesorio = (id) => { form.accesorios = form.accesorios.filter(a => a.id !== id); };

const agregarSustituto = (p) => {
    if (form.sustitutos.find(s => s.id === p.id)) return;
    form.sustitutos.push({ id: p.id, referencia: p.referencia, nombre: p.nombre });
    buscar.value = '';
};
const quitarSustituto = (id) => { form.sustitutos = form.sustitutos.filter(s => s.id !== id); };

// Guardar
const guardar = () => {
    // Aplanar accesorios y sustitutos para el backend
    const payload = { ...form.data(), accesorios: form.accesorios.map(a => ({ id: a.id, cantidad: a.cantidad })), sustitutos: form.sustitutos.map(s => s.id) };
    if (esNuevo) {
        form.transform(() => payload).post('/app/catalogo/productos');
    } else {
        form.transform(() => payload).put(`/app/catalogo/productos/${props.producto.id}`);
    }
};

const eliminar = () => {
    if (!confirm(`¿Eliminar producto ${props.producto.referencia}?`)) return;
    router.delete(`/app/catalogo/productos/${props.producto.id}`);
};

const tabs = [
    { key: 'general', label: '1. General', icon: Info },
    { key: 'comercial', label: '2. Comercial', icon: DollarSign },
    { key: 'caracteristicas', label: '3. Características', icon: Ruler },
    { key: 'ficha', label: '4. Ficha técnica', icon: FileText },
];
</script>

<template>
    <Head :title="esNuevo ? 'Nuevo producto' : `Producto ${producto.referencia}`"/>
    <AppLayout>
        <div class="max-w-6xl mx-auto space-y-4">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <Link href="/app/catalogo/productos" class="text-sm text-brand-600 hover:underline inline-flex items-center gap-1">
                        <ArrowLeft class="h-4 w-4"/> Volver a Productos
                    </Link>
                    <h1 class="text-2xl font-bold flex items-center gap-2 mt-1">
                        <Package class="h-6 w-6 text-brand-600"/>
                        {{ esNuevo ? 'Nuevo producto' : `Producto ${producto.referencia}` }}
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">Formato SIIGO Kardex Referencias · 4 pestañas.</p>
                </div>
                <div class="flex items-center gap-2">
                    <div v-if="!esNuevo" class="flex items-center gap-1 text-xs">
                        <Cloud v-if="producto.siigo_id" class="h-4 w-4 text-emerald-600"/>
                        <CloudOff v-else class="h-4 w-4 text-amber-600"/>
                        <span :class="producto.siigo_id ? 'text-emerald-700' : 'text-amber-700'">
                            {{ producto.siigo_id ? `SIIGO: ${producto.siigo_code}` : 'Pendiente SIIGO' }}
                        </span>
                    </div>
                    <button v-if="!esNuevo" @click="eliminar" class="btn-ghost text-red-500 hover:text-red-700 text-sm">
                        <Trash2 class="h-4 w-4"/>
                    </button>
                    <button @click="guardar" :disabled="form.processing"
                            class="btn-primary text-sm inline-flex items-center gap-1"
                            :class="form.processing ? 'opacity-50 cursor-wait' : ''">
                        <Save class="h-4 w-4"/> {{ form.processing ? 'Guardando…' : 'Guardar' }}
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex items-center gap-1 border-b border-surface-200 dark:border-surface-800 overflow-x-auto">
                <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
                        :class="['px-4 py-2 text-sm font-semibold border-b-2 flex items-center gap-1 whitespace-nowrap',
                                 tab === t.key ? 'border-brand-500 text-brand-600' : 'border-transparent text-surface-500 hover:text-surface-700']">
                    <component :is="t.icon" class="h-4 w-4"/> {{ t.label }}
                </button>
            </div>

            <!-- PESTAÑA 1: GENERAL -->
            <div v-if="tab === 'general'" class="card p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Referencia *</label>
                        <input v-model="form.referencia" class="input w-full font-mono" required maxlength="100"/>
                        <div v-if="form.errors.referencia" class="text-xs text-red-600">{{ form.errors.referencia }}</div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Nombre / Descripción *</label>
                        <input v-model="form.nombre" class="input w-full" required maxlength="200"/>
                        <div v-if="form.errors.nombre" class="text-xs text-red-600">{{ form.errors.nombre }}</div>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-surface-600">Descripción</label>
                    <textarea v-model="form.descripcion" rows="2" class="input w-full"></textarea>
                </div>
                <div>
                    <label class="text-xs font-semibold text-surface-600">Descripción ampliada</label>
                    <textarea v-model="form.descripcion_ampliada" rows="2" class="input w-full" placeholder="Descripción adicional (opcional · útil para servicios y productos con specs largas)"></textarea>
                </div>

                <!-- Jerarquía SIIGO -->
                <div class="border-t pt-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Clasificación SIIGO</div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Línea *</label>
                            <select v-model="form.linea_id" class="input w-full">
                                <option :value="null">— Selecciona —</option>
                                <option v-for="l in catalogos.lineas" :key="l.id" :value="l.id">{{ l.codigo }} · {{ l.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Grupo</label>
                            <select v-model="form.grupo_id" class="input w-full" :disabled="!form.linea_id">
                                <option :value="null">— Selecciona —</option>
                                <option v-for="g in grupos" :key="g.id" :value="g.id">{{ g.codigo }} · {{ g.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Subgrupo</label>
                            <select v-model="form.subgrupo_id" class="input w-full" :disabled="!form.grupo_id">
                                <option :value="null">— Selecciona —</option>
                                <option v-for="s in subgrupos" :key="s.id" :value="s.id">{{ s.codigo }} · {{ s.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Clase</label>
                            <select v-model="form.clase_id" class="input w-full" :disabled="!form.subgrupo_id">
                                <option :value="null">— Selecciona —</option>
                                <option v-for="c in clases" :key="c.id" :value="c.id">{{ c.codigo }} · {{ c.nombre }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Marca/Categoría/Colección (ERP interno) -->
                <div class="border-t pt-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Clasificación ERP interna</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Marca</label>
                            <select v-model="form.marca_id" class="input w-full">
                                <option :value="null">— Sin marca —</option>
                                <option v-for="m in catalogos.marcas" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Categoría</label>
                            <select v-model="form.categoria_id" class="input w-full">
                                <option :value="null">—</option>
                                <option v-for="c in catalogos.categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Colección</label>
                            <select v-model="form.coleccion_id" class="input w-full">
                                <option :value="null">—</option>
                                <option v-for="c in catalogos.colecciones" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Unidades + Factor conversión -->
                <div class="border-t pt-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Unidades</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Unidad de venta</label>
                            <select v-model="form.unidad_medida_id" class="input w-full">
                                <option :value="null">— Selecciona —</option>
                                <option v-for="u in catalogos.unidades" :key="u.id" :value="u.id">{{ u.codigo }} · {{ u.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Unidad de compra</label>
                            <select v-model="form.unidad_compra_id" class="input w-full">
                                <option :value="null">— Igual que venta —</option>
                                <option v-for="u in catalogos.unidades" :key="u.id" :value="u.id">{{ u.codigo }} · {{ u.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Factor conversión (compra→venta)</label>
                            <input v-model="form.factor_conversion" type="number" step="0.0001" class="input w-full" placeholder="Ej: 12 (1 caja = 12 uds)"/>
                        </div>
                    </div>
                </div>

                <!-- Aduana + Reposición -->
                <div class="border-t pt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Posición arancelaria</label>
                        <input v-model="form.posicion_arancelaria" class="input w-full font-mono" maxlength="20" placeholder="Ej: 6111.20.00.00"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Tiempo reposición máx (días)</label>
                        <input v-model="form.reposicion_max_dias" type="number" min="0" class="input w-full"/>
                    </div>
                </div>

                <!-- Flags SIIGO -->
                <div class="border-t pt-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Comportamiento</div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.activo" class="rounded"/> Activo</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.proteger_precio" class="rounded"/> Precio protegido</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.maneja_lotes" class="rounded"/> Maneja lotes</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.maneja_seriales" class="rounded"/> Maneja seriales</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.es_estadistico" class="rounded"/> Estadístico</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.requiere_talla" class="rounded"/> Requiere talla</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.es_set" class="rounded"/> Es set/kit</label>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.neto" class="rounded"/> Neto (aplica desc.)</label>
                    </div>
                </div>

                <!-- Desglose stock -->
                <div class="border-t pt-4">
                    <label class="flex items-center gap-2 text-sm font-semibold">
                        <input type="checkbox" v-model="form.desglose_stock" class="rounded"/>
                        Desglosar stock por variante (color/talla)
                    </label>
                    <div v-if="!form.desglose_stock" class="mt-2">
                        <label class="text-xs font-semibold text-surface-600">Stock inicial agregado</label>
                        <input v-model="form.stock_directo" type="number" min="0" class="input w-40"/>
                    </div>
                </div>
            </div>

            <!-- PESTAÑA 2: COMERCIAL -->
            <div v-if="tab === 'comercial'" class="card p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Impuesto</label>
                        <select v-model="form.impuesto_id" class="input w-full">
                            <option :value="null">— Selecciona —</option>
                            <option v-for="i in catalogos.impuestos" :key="i.id" :value="i.id">{{ i.nombre }} ({{ i.porcentaje }}%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Precio proveedor</label>
                        <input v-model="form.precio_proveedor" type="number" step="0.01" min="0" class="input w-full font-mono"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Rentabilidad % (control)</label>
                        <input v-model="form.rentabilidad_pct" type="number" step="0.01" min="0" max="100" class="input w-full"/>
                        <p class="text-[10px] text-surface-500 mt-1">Bloquea facturar bajo esta rentabilidad</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">% Descuento default</label>
                        <input v-model="form.descuento_default_pct" type="number" step="0.01" min="0" max="100" class="input w-full"/>
                    </div>
                </div>

                <!-- Cuentas contables -->
                <div class="border-t pt-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Cuentas contables (PUC Great Baby)</div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Cta. ingreso</label>
                            <input v-model="form.cta_ingreso" class="input w-full font-mono" maxlength="30" placeholder="4135"/>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Cta. IVA venta</label>
                            <input v-model="form.cta_iva_venta" class="input w-full font-mono" maxlength="30" placeholder="240805"/>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Cta. costo</label>
                            <input v-model="form.cta_costo" class="input w-full font-mono" maxlength="30" placeholder="6135"/>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Cta. inventario</label>
                            <input v-model="form.cta_inventario" class="input w-full font-mono" maxlength="30" placeholder="1435"/>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Cta. devolución</label>
                            <input v-model="form.cta_devolucion" class="input w-full font-mono" maxlength="30" placeholder="4175"/>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Cta. descuento</label>
                            <input v-model="form.cta_descuento" class="input w-full font-mono" maxlength="30" placeholder="5305"/>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Centro de costo</label>
                            <input v-model="form.centro_costo" class="input w-full font-mono" maxlength="30"/>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="text-xs font-semibold text-surface-600">Notas contables</label>
                        <textarea v-model="form.notas_contables" rows="2" class="input w-full"></textarea>
                    </div>
                </div>

                <!-- NIIF -->
                <div class="border-t pt-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">NIIF</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Valor gasto venta</label>
                            <input v-model="form.valor_gasto_venta_niif" type="number" step="0.01" min="0" class="input w-full font-mono"/>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-surface-600">Valor neto realizable</label>
                            <input v-model="form.valor_neto_realizable_niif" type="number" step="0.01" min="0" class="input w-full font-mono"/>
                        </div>
                    </div>
                </div>

                <!-- Accesorios y Sustitutos -->
                <div class="border-t pt-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Accesorios (facturados junto)</div>
                    <div class="space-y-1 mb-2">
                        <div v-for="a in form.accesorios" :key="a.id" class="flex items-center gap-2 text-sm p-2 rounded bg-surface-50 dark:bg-surface-900">
                            <span class="font-mono font-bold">{{ a.referencia }}</span>
                            <span class="flex-1">{{ a.nombre }}</span>
                            <input v-model="a.cantidad" type="number" min="1" class="input w-20 text-sm"/>
                            <button type="button" @click="quitarAccesorio(a.id)" class="text-red-500 hover:text-red-700"><X class="h-4 w-4"/></button>
                        </div>
                    </div>
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-surface-400"/>
                        <input v-model="buscar" class="input pl-9 w-full text-sm" placeholder="Buscar producto para agregar accesorio/sustituto…"/>
                        <div v-if="resultados.length" class="absolute z-10 left-0 right-0 mt-1 max-h-48 overflow-y-auto rounded border bg-white dark:bg-surface-900 shadow-lg">
                            <div v-for="p in resultados" :key="p.id" class="flex items-center gap-2 p-2 hover:bg-surface-50 text-sm">
                                <span class="font-mono font-bold flex-1">{{ p.referencia }} · {{ p.nombre }}</span>
                                <button type="button" @click="agregarAccesorio(p)" class="btn-ghost text-xs">+Accesorio</button>
                                <button type="button" @click="agregarSustituto(p)" class="btn-ghost text-xs">+Sustituto</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Sustitutos (alternativas si sin stock)</div>
                    <div class="space-y-1">
                        <div v-for="s in form.sustitutos" :key="s.id" class="flex items-center gap-2 text-sm p-2 rounded bg-surface-50 dark:bg-surface-900">
                            <span class="font-mono font-bold">{{ s.referencia }}</span>
                            <span class="flex-1">{{ s.nombre }}</span>
                            <button type="button" @click="quitarSustituto(s.id)" class="text-red-500 hover:text-red-700"><X class="h-4 w-4"/></button>
                        </div>
                        <div v-if="!form.sustitutos.length" class="text-xs text-surface-500">Sin sustitutos definidos.</div>
                    </div>
                </div>
            </div>

            <!-- PESTAÑA 3: CARACTERÍSTICAS -->
            <div v-if="tab === 'caracteristicas'" class="card p-5 space-y-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600">Dimensiones (para facturación electrónica y logística)</div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Peso (g)</label>
                        <input v-model="form.peso_gr" type="number" step="0.01" min="0" class="input w-full font-mono"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Alto (cm)</label>
                        <input v-model="form.alto_cm" type="number" step="0.01" min="0" class="input w-full font-mono"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Ancho (cm)</label>
                        <input v-model="form.ancho_cm" type="number" step="0.01" min="0" class="input w-full font-mono"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600">Largo (cm)</label>
                        <input v-model="form.largo_cm" type="number" step="0.01" min="0" class="input w-full font-mono"/>
                    </div>
                </div>
                <div class="text-xs text-surface-500 pt-2">
                    Campos de impoconsumo, estampillas, CUM (farmacia) e hortofruticultura NO aplican al negocio Great Baby (ropa y accesorios bebé). Se pueden habilitar si algún día se venden productos de esos sectores.
                </div>
            </div>

            <!-- PESTAÑA 4: FICHA TÉCNICA -->
            <div v-if="tab === 'ficha'" class="card p-5 space-y-4">
                <div>
                    <label class="text-xs font-semibold text-surface-600">Ficha técnica / Cuidados / Conservación</label>
                    <textarea v-model="form.ficha_tecnica" rows="10" class="input w-full font-mono text-xs"
                              placeholder="Especial para ropa bebé: instrucciones de lavado, temperatura máxima, planchado, secado, ingredientes textiles, certificaciones OEKO-TEX, etc.

Este texto aparece al momento de facturar y puede modificarse por línea."></textarea>
                    <p class="text-xs text-surface-500 mt-1">Se muestra en el detalle del producto en factura y catálogo B2B.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
