<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Package, Plus, Search, Eye, Pencil, Trash2, Cloud, CloudOff, Lock,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useMoney } from '@/composables/useMoney';

const props = defineProps({
    filtros: { type: Object, required: true },
    kpis: { type: Object, required: true },
    productos: { type: Object, required: true },
    lineas: { type: Array, required: true },
});

const { money } = useMoney();

const q = ref(props.filtros.q);
const activo = ref(props.filtros.activo);
const lineaId = ref(props.filtros.linea_id);
const sinSiigo = ref(!!props.filtros.sin_siigo);

let debounce;
const filtrar = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/app/catalogo/productos', {
            q: q.value || null,
            activo: activo.value === '' ? null : activo.value,
            linea_id: lineaId.value || null,
            sin_siigo: sinSiigo.value ? 1 : null,
        }, { preserveScroll: true, preserveState: true, replace: true });
    }, 300);
};

const eliminar = (p) => {
    if (! confirm(`¿Eliminar producto ${p.referencia}?\n\nSolo se permite si no tiene movimientos de kardex.`)) return;
    router.delete(`/app/catalogo/productos/${p.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Productos · Kardex Referencias SIIGO"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Package class="h-6 w-6 text-brand-600"/>
                        Productos · Kardex Referencias SIIGO
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">
                        Catálogo de referencias con las 4 pestañas SIIGO (General · Comercial · Características · Ficha técnica).
                    </p>
                </div>
                <Link href="/app/catalogo/productos/nuevo" class="btn-primary text-sm inline-flex items-center gap-1">
                    <Plus class="h-4 w-4"/> Nuevo producto
                </Link>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500">Total</div>
                    <div class="text-2xl font-bold mt-1">{{ kpis.total.toLocaleString('es-CO') }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500">Activos</div>
                    <div class="text-2xl font-bold mt-1 text-emerald-600">{{ kpis.activos.toLocaleString('es-CO') }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500">Sin sincronizar SIIGO</div>
                    <div class="text-2xl font-bold mt-1 text-amber-600">{{ kpis.sin_siigo.toLocaleString('es-CO') }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500">Precio protegido</div>
                    <div class="text-2xl font-bold mt-1">{{ kpis.protegidos.toLocaleString('es-CO') }}</div>
                    <div class="text-[10px] text-surface-400">no editable al facturar</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card p-3">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                    <div class="relative md:col-span-2">
                        <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-surface-400"/>
                        <input v-model="q" @input="filtrar" placeholder="Buscar por referencia, nombre o code SIIGO…"
                               class="input pl-9 w-full text-sm"/>
                    </div>
                    <select v-model="lineaId" @change="filtrar" class="input text-sm">
                        <option value="">Todas las líneas</option>
                        <option v-for="l in lineas" :key="l.id" :value="l.id">{{ l.codigo }} · {{ l.nombre }}</option>
                    </select>
                    <div class="flex gap-2">
                        <select v-model="activo" @change="filtrar" class="input text-sm flex-1">
                            <option value="">Todos</option>
                            <option value="1">Solo activos</option>
                            <option value="0">Solo inactivos</option>
                        </select>
                        <label class="flex items-center gap-1 text-xs whitespace-nowrap">
                            <input type="checkbox" v-model="sinSiigo" @change="filtrar" class="rounded"/>
                            Sin SIIGO
                        </label>
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="card overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="text-[10px] uppercase text-surface-500 border-b bg-surface-50 dark:bg-surface-900">
                        <tr>
                            <th class="text-left p-2 w-32">Referencia</th>
                            <th class="text-left p-2">Nombre</th>
                            <th class="text-left p-2">Marca</th>
                            <th class="text-left p-2">Línea</th>
                            <th class="text-right p-2">Precio prov.</th>
                            <th class="text-center p-2">SIIGO</th>
                            <th class="text-center p-2">Activo</th>
                            <th class="text-right p-2 w-24">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="p in productos.data" :key="p.id" class="hover:bg-surface-50">
                            <td class="p-2 font-mono font-bold text-brand-600">
                                <Link :href="`/app/catalogo/productos/${p.id}`" class="hover:underline">{{ p.referencia }}</Link>
                            </td>
                            <td class="p-2">
                                {{ p.nombre }}
                                <Lock v-if="p.proteger_precio" class="h-3 w-3 inline text-amber-600 ml-1" title="Precio protegido"/>
                            </td>
                            <td class="p-2 text-xs text-surface-500">{{ p.marca || '—' }}</td>
                            <td class="p-2 text-xs">{{ p.linea || '—' }}</td>
                            <td class="p-2 text-right font-mono">{{ money(p.precio_proveedor) }}</td>
                            <td class="p-2 text-center">
                                <Cloud v-if="p.siigo_id" class="h-4 w-4 inline text-emerald-600" :title="`SIIGO: ${p.siigo_code}`"/>
                                <CloudOff v-else class="h-4 w-4 inline text-amber-600" title="Pendiente sync"/>
                            </td>
                            <td class="p-2 text-center">
                                <span :class="p.activo ? 'text-emerald-600' : 'text-red-500'" class="text-lg">●</span>
                            </td>
                            <td class="p-2 text-right whitespace-nowrap">
                                <Link :href="`/app/catalogo/productos/${p.id}`" class="text-brand-600 hover:text-brand-700 p-1 inline-block" title="Ver/Editar">
                                    <Pencil class="h-4 w-4"/>
                                </Link>
                                <button @click="eliminar(p)" class="text-red-500 hover:text-red-700 p-1" title="Eliminar">
                                    <Trash2 class="h-4 w-4"/>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!productos.data.length">
                            <td colspan="8" class="p-8 text-center text-surface-500 text-sm">
                                Sin productos con esos filtros.
                            </td>
                        </tr>
                    </tbody>
                </table>
                <!-- Paginación -->
                <div v-if="productos.last_page > 1" class="flex items-center justify-between p-3 border-t text-xs">
                    <div class="text-surface-500">{{ productos.from }}–{{ productos.to }} de {{ productos.total }}</div>
                    <div class="flex gap-1">
                        <template v-for="l in productos.links" :key="l.label">
                            <button v-if="l.url" @click="router.get(l.url, {}, { preserveScroll: true })"
                                    :class="['px-2 py-1 rounded', l.active ? 'bg-brand-600 text-white' : 'hover:bg-surface-100']"
                                    v-html="l.label"/>
                            <span v-else :class="['px-2 py-1 text-surface-400']" v-html="l.label"/>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
