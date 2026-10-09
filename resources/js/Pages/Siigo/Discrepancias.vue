<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, RefreshCw, Cloud, CloudOff, ArrowLeft, Loader2, Check } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useMoney } from '@/composables/useMoney';
import { mensajeDeError } from '@/composables/useMensajeError';
import axios from 'axios';

const { money } = useMoney();

const cargando = ref(false);
const resultado = ref(null);
const error = ref(null);

// PROD-14 · dispara el cálculo en el servidor. Puede tardar varios segundos
// porque recorre /v1/products de SIIGO en bloques de 25.
const calcular = async () => {
    cargando.value = true;
    error.value = null;
    try {
        const { data } = await axios.get('/app/siigo/discrepancias/calcular');
        resultado.value = data;
    } catch (e) {
        error.value = mensajeDeError(e, 'No pude calcular las diferencias con SIIGO');
    } finally {
        cargando.value = false;
    }
};

// Formato amigable para cada diferencia (acepta números, strings, booleanos).
const fmt = (v) => {
    if (v === true) return 'Sí';
    if (v === false) return 'No';
    if (v === null || v === '') return '—';
    if (typeof v === 'number') return money(v);
    return String(v);
};

const hayDiferencias = computed(() => resultado.value && resultado.value.con_diferencias > 0);
const hayHuerfanos = computed(() => resultado.value && resultado.value.huerfanos_local?.length > 0);
const hayZombies = computed(() => resultado.value && resultado.value.zombies_siigo?.length > 0);
const todoOk = computed(() =>
    resultado.value &&
    !hayDiferencias.value &&
    !hayHuerfanos.value &&
    !hayZombies.value
);
</script>

<template>
    <Head title="Discrepancias ERP vs SIIGO"/>
    <AppLayout>
        <div class="space-y-4 max-w-6xl">
            <div class="flex items-center gap-3">
                <Link href="/app/siigo" class="text-sm inline-flex items-center gap-1 text-surface-500 hover:text-surface-700">
                    <ArrowLeft class="h-4 w-4"/> Volver a SIIGO
                </Link>
            </div>

            <div class="card p-5">
                <div class="flex items-start gap-3">
                    <div class="p-2 bg-amber-50 rounded-lg">
                        <AlertTriangle class="h-6 w-6 text-amber-600"/>
                    </div>
                    <div class="flex-1">
                        <h1 class="text-xl font-bold text-surface-800">Discrepancias ERP ↔ SIIGO</h1>
                        <p class="text-sm text-surface-500 mt-1">
                            Compara en vivo nombre, precio, estado activo y marca de cada producto. Al ejecutar
                            revisa SIIGO en bloques de 25 productos · puede tardar unos segundos con catálogos grandes.
                        </p>
                    </div>
                    <button @click="calcular" :disabled="cargando"
                            class="btn-primary text-sm inline-flex items-center gap-2 whitespace-nowrap disabled:opacity-60">
                        <Loader2 v-if="cargando" class="h-4 w-4 animate-spin"/>
                        <RefreshCw v-else class="h-4 w-4"/>
                        {{ cargando ? 'Calculando…' : (resultado ? 'Recalcular' : 'Calcular ahora') }}
                    </button>
                </div>
            </div>

            <div v-if="error" class="card p-4 border-l-4 border-rose-500 bg-rose-50 text-rose-700 text-sm">
                {{ error }}
            </div>

            <!-- Resumen -->
            <div v-if="resultado" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card p-4">
                    <div class="text-xs text-surface-500 uppercase font-semibold">En SIIGO</div>
                    <div class="text-2xl font-bold text-sky-600">{{ resultado.total_siigo }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs text-surface-500 uppercase font-semibold">En ERP (con SIIGO id)</div>
                    <div class="text-2xl font-bold text-surface-800">{{ resultado.total_locales }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs text-surface-500 uppercase font-semibold">Con diferencias</div>
                    <div class="text-2xl font-bold" :class="hayDiferencias ? 'text-amber-600' : 'text-emerald-600'">
                        {{ resultado.con_diferencias }}
                    </div>
                </div>
                <div class="card p-4">
                    <div class="text-xs text-surface-500 uppercase font-semibold">Generado</div>
                    <div class="text-xs text-surface-700 mt-1">{{ resultado.generado_at }}</div>
                </div>
            </div>

            <!-- Todo en orden -->
            <div v-if="todoOk" class="card p-8 text-center">
                <Check class="h-10 w-10 text-emerald-500 mx-auto"/>
                <h2 class="font-bold text-lg mt-3 text-emerald-700">Todo en orden</h2>
                <p class="text-sm text-surface-500 mt-1">Los productos del ERP coinciden con lo que hay en SIIGO.</p>
            </div>

            <!-- Diferencias -->
            <div v-if="hayDiferencias" class="card overflow-hidden">
                <div class="p-4 border-b bg-amber-50/50">
                    <h2 class="font-bold text-surface-800 inline-flex items-center gap-2">
                        <AlertTriangle class="h-4 w-4 text-amber-600"/>
                        Productos con diferencias
                        <span class="text-xs font-normal text-surface-500">({{ resultado.filas.length }})</span>
                    </h2>
                </div>
                <table v-tabla-movil class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b bg-surface-50">
                        <tr>
                            <th class="text-left p-3 w-36">Referencia</th>
                            <th class="text-left p-3">Nombre</th>
                            <th class="text-left p-3">Diferencia</th>
                            <th class="text-right p-3">ERP</th>
                            <th class="text-right p-3">SIIGO</th>
                            <th class="text-left p-3 w-28">Última sync</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <template v-for="f in resultado.filas" :key="f.producto_id">
                            <tr v-for="(d, i) in f.diferencias" :key="i" class="hover:bg-amber-50/30">
                                <td v-if="i === 0" :rowspan="f.diferencias.length"
                                    class="p-3 font-mono font-bold text-brand-600 border-r align-top">
                                    <Link :href="`/app/catalogo/productos/${f.producto_id}`" class="hover:underline">
                                        {{ f.referencia }}
                                    </Link>
                                </td>
                                <td v-if="i === 0" :rowspan="f.diferencias.length"
                                    class="p-3 border-r align-top">
                                    {{ f.nombre }}
                                </td>
                                <td class="p-3">
                                    <span class="inline-block px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800 uppercase font-semibold">
                                        {{ d.campo }}
                                    </span>
                                </td>
                                <td class="p-3 text-right font-mono text-xs text-surface-700">{{ fmt(d.erp) }}</td>
                                <td class="p-3 text-right font-mono text-xs text-sky-700">{{ fmt(d.siigo) }}</td>
                                <td v-if="i === 0" :rowspan="f.diferencias.length"
                                    class="p-3 text-xs text-surface-500 align-top">
                                    {{ f.siigo_sync_at || '—' }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Huérfanos locales · siigo_id ya no existe en SIIGO -->
            <div v-if="hayHuerfanos" class="card overflow-hidden">
                <div class="p-4 border-b bg-rose-50/50">
                    <h2 class="font-bold text-surface-800 inline-flex items-center gap-2">
                        <CloudOff class="h-4 w-4 text-rose-600"/>
                        Huérfanos locales
                        <span class="text-xs font-normal text-surface-500">
                            ({{ resultado.huerfanos_local.length }}) · productos del ERP cuyo siigo_id ya no existe en SIIGO
                        </span>
                    </h2>
                </div>
                <table v-tabla-movil class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b bg-surface-50">
                        <tr>
                            <th class="text-left p-3 w-36">Referencia</th>
                            <th class="text-left p-3">Nombre</th>
                            <th class="text-left p-3 w-28">SIIGO code</th>
                            <th class="text-left p-3 w-20">SIIGO id</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="h in resultado.huerfanos_local" :key="h.id" class="hover:bg-rose-50/30">
                            <td class="p-3 font-mono font-bold text-brand-600">
                                <Link :href="`/app/catalogo/productos/${h.id}`" class="hover:underline">{{ h.referencia }}</Link>
                            </td>
                            <td class="p-3">{{ h.nombre }}</td>
                            <td class="p-3 font-mono text-xs text-surface-500">{{ h.siigo_code || '—' }}</td>
                            <td class="p-3 font-mono text-xs text-surface-400">{{ h.siigo_id }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Zombies SIIGO · están allá y no en ERP -->
            <div v-if="hayZombies" class="card overflow-hidden">
                <div class="p-4 border-b bg-sky-50/50">
                    <h2 class="font-bold text-surface-800 inline-flex items-center gap-2">
                        <Cloud class="h-4 w-4 text-sky-600"/>
                        En SIIGO pero no en el ERP
                        <span class="text-xs font-normal text-surface-500">
                            ({{ resultado.zombies_siigo.length }}) · tope de 100 para no reventar la vista
                        </span>
                    </h2>
                </div>
                <table v-tabla-movil class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b bg-surface-50">
                        <tr>
                            <th class="text-left p-3 w-28">SIIGO code</th>
                            <th class="text-left p-3">Nombre</th>
                            <th class="text-center p-3 w-24">Activo</th>
                            <th class="text-left p-3 w-32">SIIGO id</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="z in resultado.zombies_siigo" :key="z.siigo_id" class="hover:bg-sky-50/30">
                            <td class="p-3 font-mono text-xs">{{ z.siigo_code }}</td>
                            <td class="p-3">{{ z.nombre }}</td>
                            <td class="p-3 text-center">
                                <span v-if="z.active" class="text-emerald-600">●</span>
                                <span v-else class="text-rose-500">●</span>
                            </td>
                            <td class="p-3 font-mono text-xs text-surface-400">{{ z.siigo_id }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Vacío inicial -->
            <div v-if="! resultado && ! cargando" class="card p-8 text-center text-surface-500 text-sm">
                Hacé click en "Calcular ahora" para traer el estado actual de SIIGO y comparar.
            </div>
        </div>
    </AppLayout>
</template>
