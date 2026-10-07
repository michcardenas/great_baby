<script setup>
// INV-B2 · Modal genérico para subir Excel/CSV masivo (conteos/traslados/alertas).
// Recibe `endpoint` (POST), `tipo` ('conteo'|'traslado'|'alerta') para la plantilla
// y payload extra opcional. Emite 'cerrar' y 'ok' tras importar.
import { ref } from 'vue';
import { Upload, Download, X, FileSpreadsheet, CheckCircle2, AlertCircle, Copy } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    abierto: { type: Boolean, default: false },
    endpoint: { type: String, required: true },
    tipo: { type: String, required: true },
    titulo: { type: String, default: 'Import masivo Excel' },
    ayuda: { type: String, default: 'Columna A: SKU/referencia · Columna B: cantidad.' },
    extraPayload: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['cerrar', 'ok']);

const archivo = ref(null);
const subiendo = ref(false);
const progreso = ref(0);
const resultado = ref(null);

const seleccionar = (e) => { archivo.value = e.target.files[0] || null; resultado.value = null; progreso.value = 0; };

const subir = async () => {
    if (!archivo.value) return;
    subiendo.value = true; resultado.value = null; progreso.value = 0;
    const fd = new FormData();
    fd.append('archivo', archivo.value);
    Object.entries(props.extraPayload).forEach(([k, v]) => {
        if (v !== null && v !== undefined) fd.append(k, v);
    });
    try {
        const { data } = await axios.post(props.endpoint, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
            // FIX-S0 · barra de progreso real durante el upload (antes solo
            // decía "Subiendo…" y en 5MB/3G parecía congelado).
            onUploadProgress: (e) => {
                if (e.total) progreso.value = Math.round((e.loaded / e.total) * 100);
            },
        });
        resultado.value = data;
        if (data.ok) emit('ok', data);
    } catch (e) {
        resultado.value = {
            ok: false,
            mensaje: e.response?.data?.mensaje || e.response?.data?.message || e.message,
        };
    } finally { subiendo.value = false; }
};

// FIX-S0 · copiar errores al portapapeles para pegarlos en WhatsApp.
const copiarErrores = async () => {
    const txt = [
        ...(resultado.value?.no_matcheadas || []),
        ...(resultado.value?.invalidas || []),
    ].join('\n');
    if (!txt) return;
    try {
        await navigator.clipboard.writeText(txt);
        alert('Errores copiados al portapapeles.');
    } catch { alert('No pude copiar · marcá el texto a mano.'); }
};

const descargarPlantilla = () => {
    window.location.href = `/app/inventario/plantilla-masiva.xlsx?tipo=${props.tipo}`;
};

const cerrar = () => {
    // FIX-S0 · no permitir cerrar mientras sube (perdería el callback).
    if (subiendo.value) return;
    archivo.value = null; resultado.value = null; progreso.value = 0;
    emit('cerrar');
};
</script>

<template>
    <div v-if="abierto" class="fixed inset-0 bg-black/50 flex items-start justify-center p-4 z-50 overflow-y-auto"
         @click.self="cerrar">
        <div class="bg-white rounded-xl shadow-xl max-w-xl w-full my-8">
            <div class="p-4 border-b flex items-center justify-between">
                <h2 class="text-lg font-bold text-surface-800 flex items-center gap-2">
                    <FileSpreadsheet class="h-5 w-5 text-emerald-600"/> {{ titulo }}
                </h2>
                <button @click="cerrar" class="p-1 rounded hover:bg-surface-100">
                    <X class="h-4 w-4"/>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <p class="text-sm text-surface-600">{{ ayuda }}</p>

                <button type="button" @click="descargarPlantilla"
                        class="text-xs inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-emerald-500 text-emerald-700 hover:bg-emerald-50">
                    <Download class="h-3.5 w-3.5"/> Descargar plantilla Excel ({{ tipo }})
                </button>

                <div class="border-2 border-dashed border-surface-300 rounded-lg p-6 text-center">
                    <Upload class="h-8 w-8 mx-auto text-surface-400 mb-2"/>
                    <input type="file" accept=".xlsx,.xls,.csv,.txt" @change="seleccionar"
                           class="text-sm w-full"/>
                    <p v-if="archivo" class="text-xs text-emerald-700 mt-2">
                        <strong>{{ archivo.name }}</strong> · {{ (archivo.size / 1024).toFixed(1) }} KB
                    </p>
                </div>

                <!-- FIX-S0 · barra de progreso durante el upload -->
                <div v-if="subiendo" class="space-y-1">
                    <div class="w-full bg-surface-200 rounded-full h-2 overflow-hidden">
                        <div class="bg-emerald-500 h-full transition-all" :style="{ width: `${progreso}%` }"></div>
                    </div>
                    <div class="text-[11px] text-surface-500 text-center">
                        {{ progreso < 100 ? `Subiendo archivo… ${progreso}%` : 'Procesando filas en el servidor…' }}
                    </div>
                </div>

                <div v-if="resultado" :class="['card p-3 border-l-4 text-sm',
                    resultado.ok ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-rose-500 bg-rose-50 text-rose-800']">
                    <div class="font-semibold flex items-center gap-1.5">
                        <CheckCircle2 v-if="resultado.ok" class="h-4 w-4"/>
                        <AlertCircle v-else class="h-4 w-4"/>
                        {{ resultado.mensaje }}
                    </div>
                    <ul v-if="resultado.no_matcheadas?.length" class="mt-2 text-xs space-y-0.5 max-h-32 overflow-y-auto">
                        <li v-for="(m, i) in resultado.no_matcheadas" :key="`nm${i}`" class="text-amber-700">· {{ m }}</li>
                    </ul>
                    <ul v-if="resultado.invalidas?.length" class="mt-2 text-xs space-y-0.5 max-h-32 overflow-y-auto">
                        <li v-for="(m, i) in resultado.invalidas" :key="`iv${i}`" class="text-rose-700">· {{ m }}</li>
                    </ul>
                    <!-- FIX-S0 · copiar lista de errores al portapapeles -->
                    <button v-if="(resultado.no_matcheadas?.length || resultado.invalidas?.length)"
                            @click="copiarErrores"
                            class="mt-2 text-xs inline-flex items-center gap-1 px-2 py-1 rounded border hover:bg-white">
                        <Copy class="h-3 w-3"/> Copiar lista de errores
                    </button>
                </div>
            </div>

            <div class="p-4 border-t flex items-center justify-end gap-2">
                <button @click="cerrar" class="text-sm px-4 py-2 rounded-lg hover:bg-surface-100">Cerrar</button>
                <button @click="subir" :disabled="!archivo || subiendo"
                        class="btn-primary text-sm inline-flex items-center gap-1.5 disabled:opacity-50">
                    <Upload class="h-4 w-4"/>
                    {{ subiendo ? 'Subiendo…' : 'Importar' }}
                </button>
            </div>
        </div>
    </div>
</template>
