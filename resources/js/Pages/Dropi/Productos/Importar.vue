<script setup>
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Upload, FileSpreadsheet, Download, Loader2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const archivo = ref(null);
const procesando = ref(false);
const errorMsg = ref('');
const progreso = ref(0);

const onFile = (e) => { archivo.value = e.target.files[0]; };

const importar = () => {
    if (!archivo.value || procesando.value) return;
    procesando.value = true;
    progreso.value = 0;
    errorMsg.value = '';
    router.post('/app/dropi/productos/importar', {
        archivo: archivo.value,
    }, {
        forceFormData: true,
        // U22 · progreso real del upload.
        onProgress: (e) => { progreso.value = Math.round((e?.percentage ?? 0)); },
        onError: (e) => { errorMsg.value = Object.values(e).flat()[0] || 'Error al importar'; },
        onFinish: () => { procesando.value = false; archivo.value = null; progreso.value = 0; },
    });
};
</script>

<template>
    <Head title="Importar productos"/>
    <AppLayout>
        <div class="max-w-3xl mx-auto space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <Upload class="h-6 w-6 text-brand-600"/>
                Importar productos desde Excel/CSV
            </h1>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-800 dark:text-emerald-300 text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="p-3 rounded-lg bg-red-500/15 border-l-4 border-red-500 text-red-700 dark:text-red-300 text-sm">
                {{ $page.props.flash.error }}
            </div>
            <div v-if="errorMsg" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/40 border-l-4 border-red-500 text-red-700 dark:text-red-300 text-sm">
                {{ errorMsg }}
            </div>

            <div class="card p-6">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">Instrucciones</div>
                <p class="text-sm text-surface-600 mb-4">
                    El archivo debe tener encabezado en la primera fila con las columnas:
                </p>
                <div class="bg-surface-100 dark:bg-surface-800 rounded p-3 font-mono text-xs mb-4">
                    referencia, nombre, precio_proveedor
                </div>
                <p class="text-xs text-surface-500 mb-4">
                    Si la referencia ya existe, el producto se actualiza. Si no, se crea nuevo. Formatos aceptados: <b>.xlsx</b>, <b>.csv</b>, <b>.txt</b>.
                </p>
                <a href="/dropi/plantilla/productos" target="_blank" class="btn-ghost text-sm inline-flex mb-6">
                    <Download class="h-4 w-4"/> Descargar plantilla vacía
                </a>

                <div class="border-2 border-dashed border-surface-300 rounded-lg p-8 text-center">
                    <FileSpreadsheet class="h-10 w-10 text-surface-400 mx-auto mb-2"/>
                    <input type="file" id="archivo" accept=".xlsx,.csv,.txt" @change="onFile" class="hidden"/>
                    <label for="archivo" class="btn-primary cursor-pointer inline-flex">
                        <Upload class="h-4 w-4"/> Seleccionar archivo
                    </label>
                    <p v-if="archivo" class="mt-3 text-sm">{{ archivo.name }} ({{ (archivo.size / 1024).toFixed(1) }} KB)</p>
                </div>

                <button @click="importar" :disabled="!archivo || procesando" class="btn-primary w-full mt-4 disabled:opacity-50">
                    <Loader2 v-if="procesando" class="h-4 w-4 animate-spin"/>
                    <Upload v-else class="h-4 w-4"/>
                    {{ procesando ? `Importando… ${progreso}%` : 'Importar productos' }}
                </button>

                <!-- Barra de progreso -->
                <div v-if="procesando" class="mt-3 h-2 bg-surface-100 dark:bg-surface-800 rounded overflow-hidden">
                    <div class="h-full bg-brand-500 transition-all" :style="{ width: progreso + '%' }"></div>
                </div>
            </div>

            <div v-if="page.props.flash?.errores?.length" class="card p-4 border-l-4 border-red-500">
                <div class="text-xs uppercase font-bold text-red-600 mb-2">Errores durante importación ({{ page.props.flash.errores.length }})</div>
                <ul class="text-sm space-y-1">
                    <li v-for="e in page.props.flash.errores" :key="e.fila">
                        <b>Fila {{ e.fila }}:</b> {{ e.motivo }}
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
