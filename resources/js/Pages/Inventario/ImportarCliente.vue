<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Upload, FileSpreadsheet, CheckCircle2, AlertTriangle, Loader2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    bodegas: { type: Array, required: true },
    flash: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
});

const form = useForm({
    archivo: null,
    bodega_id: props.bodegas[0]?.id ?? null,
    hoja: 'Hoja1',
    dry_run: false,
});

const dragActive = ref(false);
const nombreArchivo = computed(() => form.archivo?.name || null);
const tamanoArchivo = computed(() => form.archivo
    ? (form.archivo.size / 1024).toFixed(1) + ' KB'
    : null);

const seleccionar = (file) => {
    if (!file) return;
    if (!/\.(xlsx|xls)$/i.test(file.name)) {
        alert('El archivo debe ser .xlsx o .xls');
        return;
    }
    form.archivo = file;
};

const onFileInput = (e) => seleccionar(e.target.files[0]);
const onDrop = (e) => {
    e.preventDefault();
    dragActive.value = false;
    seleccionar(e.dataTransfer.files[0]);
};

const enviar = (dryRun = false) => {
    if (!form.archivo || !form.bodega_id) {
        alert('Elige un archivo y una bodega antes de enviar.');
        return;
    }
    form.dry_run = dryRun;
    form.post('/app/inventario/importar-cliente', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (!dryRun) form.reset('archivo');
        },
    });
};
</script>

<template>
    <Head title="Importar inventario del cliente"/>
    <AppLayout>
        <div class="max-w-3xl mx-auto space-y-4 p-4">

            <div class="card p-6">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-brand-500/10 text-brand-600">
                        <Upload class="h-6 w-6"/>
                    </div>
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold">Importar inventario del cliente</h1>
                        <p class="text-sm text-surface-500 mt-1">
                            Sube el Excel formato <strong>INVENTARIO DR REPORTE</strong> (134 productos por categoría).
                            Los productos se crean como <strong>agregados</strong> (colores surtidos, sin desglose por variante)
                            con el stock inicial en la bodega que elijas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Flash de resultado -->
            <div v-if="$page.props.flash?.flash" class="card p-4 border-l-4"
                 :class="$page.props.flash.flash.type === 'success' ? 'border-l-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' : 'border-l-red-500 bg-red-50 dark:bg-red-900/20'">
                <div class="flex items-start gap-3">
                    <CheckCircle2 v-if="$page.props.flash.flash.type === 'success'" class="h-5 w-5 text-emerald-600 flex-shrink-0 mt-0.5"/>
                    <AlertTriangle v-else class="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5"/>
                    <div>
                        <div class="font-semibold">{{ $page.props.flash.flash.title }}</div>
                        <div class="text-sm text-surface-700 dark:text-surface-300 mt-1">{{ $page.props.flash.flash.body }}</div>
                    </div>
                </div>
            </div>

            <!-- Errores de validación -->
            <div v-if="Object.keys($page.props.errors || {}).length" class="card p-4 border-l-4 border-l-red-500 bg-red-50 dark:bg-red-900/20">
                <div class="flex items-start gap-3">
                    <AlertTriangle class="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5"/>
                    <div class="text-sm">
                        <div class="font-semibold text-red-900 dark:text-red-200">No pude procesar el archivo</div>
                        <ul class="mt-1 list-disc list-inside text-red-800 dark:text-red-300">
                            <li v-for="(err, k) in $page.props.errors" :key="k">{{ Array.isArray(err) ? err[0] : err }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card p-6 space-y-4">
                <div>
                    <label class="text-sm font-semibold block mb-2">1. Bodega donde cargar el inventario</label>
                    <select v-model="form.bodega_id" class="input w-full">
                        <option v-for="b in bodegas" :key="b.id" :value="b.id">{{ b.label }}</option>
                    </select>
                    <p class="text-xs text-surface-500 mt-1">
                        Todos los productos entrarán con su stock inicial aquí.
                    </p>
                </div>

                <div>
                    <label class="text-sm font-semibold block mb-2">2. Nombre de la hoja del Excel</label>
                    <input v-model="form.hoja" type="text" class="input w-full" placeholder="Hoja1"/>
                    <p class="text-xs text-surface-500 mt-1">
                        Por defecto <code>Hoja1</code>. Si tu Excel tiene otro nombre (ej. <code>REPORTE</code>), cámbialo aquí.
                    </p>
                </div>

                <div>
                    <label class="text-sm font-semibold block mb-2">3. Archivo Excel</label>
                    <div
                        @dragenter.prevent="dragActive = true"
                        @dragover.prevent="dragActive = true"
                        @dragleave.prevent="dragActive = false"
                        @drop="onDrop"
                        :class="[
                            'border-2 border-dashed rounded-lg p-8 text-center transition-colors',
                            dragActive ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-surface-300 dark:border-surface-700',
                            nombreArchivo ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-500' : ''
                        ]"
                    >
                        <FileSpreadsheet v-if="nombreArchivo" class="h-12 w-12 mx-auto text-emerald-600"/>
                        <Upload v-else class="h-12 w-12 mx-auto text-surface-400"/>
                        <div v-if="nombreArchivo" class="mt-3 font-semibold text-emerald-700 dark:text-emerald-300">
                            {{ nombreArchivo }} · {{ tamanoArchivo }}
                        </div>
                        <div v-else class="mt-3 text-sm text-surface-500">
                            Arrastra el archivo aquí o
                            <label class="text-brand-600 font-semibold cursor-pointer hover:underline">
                                selecciónalo
                                <input type="file" @change="onFileInput" accept=".xlsx,.xls" class="hidden"/>
                            </label>
                        </div>
                        <div class="text-xs text-surface-400 mt-1">Formatos: .xlsx, .xls · Max 10 MB</div>
                    </div>
                </div>

                <div class="pt-4 border-t border-surface-200 dark:border-surface-800 flex flex-col sm:flex-row gap-2">
                    <button
                        @click="enviar(true)"
                        :disabled="!form.archivo || form.processing"
                        class="btn-ghost flex-1 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        <Loader2 v-if="form.processing && form.dry_run" class="h-4 w-4 animate-spin"/>
                        Probar sin guardar (dry-run)
                    </button>
                    <button
                        @click="enviar(false)"
                        :disabled="!form.archivo || form.processing"
                        class="btn-primary flex-1 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        <Loader2 v-if="form.processing && !form.dry_run" class="h-4 w-4 animate-spin"/>
                        <Upload v-else class="h-4 w-4"/>
                        Cargar inventario definitivo
                    </button>
                </div>
                <p class="text-xs text-surface-500 text-center">
                    Recomendado: primero <strong>Probar sin guardar</strong> para revisar el conteo, luego cargar.
                </p>
            </div>

            <div class="card p-4 text-sm text-surface-500 space-y-2">
                <div class="font-semibold text-surface-700 dark:text-surface-200">¿Qué hace este importador?</div>
                <ul class="list-disc list-inside space-y-1">
                    <li>Lee filas de <strong>categoría</strong> (solo columna A con nombre) y <strong>producto</strong> (referencia + descripción + existencia + variación).</li>
                    <li>Crea/actualiza cada producto como <strong>agregado</strong> (colores surtidos).</li>
                    <li>Inserta el stock inicial en el kardex de la bodega elegida (idempotente: puedes re-correr sin duplicar).</li>
                    <li>Salta productos que ya existen como <strong>granulares</strong> (para no romper el toggle).</li>
                    <li>Ignora filas de totales, fechas y fila de header.</li>
                </ul>
            </div>

        </div>
    </AppLayout>
</template>
