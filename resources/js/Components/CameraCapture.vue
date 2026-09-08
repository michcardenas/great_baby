<script setup>
import { ref, onBeforeUnmount, watch } from 'vue';
import { Camera, Upload, X } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'captured']);

const videoEl = ref(null);
const canvasEl = ref(null);
const fileInput = ref(null);
const stream = ref(null);
const error = ref('');
const ready = ref(false);

const abrir = async () => {
    error.value = '';
    ready.value = false;
    try {
        if (! (navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) {
            error.value = 'Tu navegador no expone la API de cámara. Usá "Subir archivo".';
            return;
        }
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 960 } },
            audio: false,
        });
        if (videoEl.value) {
            videoEl.value.srcObject = stream.value;
            videoEl.value.onloadedmetadata = () => { ready.value = true; };
        }
    } catch (e) {
        error.value = 'Motivo: ' + (e.message || e.name) + '. Podés subir la foto desde archivo (o desde la cámara de tu celu).';
    }
};

const cerrar = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(t => t.stop());
        stream.value = null;
    }
    ready.value = false;
    emit('close');
};

const capturar = () => {
    if (! ready.value || ! videoEl.value?.videoWidth) return;
    const c = canvasEl.value;
    c.width = videoEl.value.videoWidth;
    c.height = videoEl.value.videoHeight;
    c.getContext('2d').drawImage(videoEl.value, 0, 0);
    const dataUri = c.toDataURL('image/jpeg', 0.82);
    emit('captured', dataUri);
    cerrar();
};

const subirArchivo = (e) => {
    const f = e.target.files?.[0];
    if (! f) return;
    if (f.size > 3_000_000) {
        error.value = 'La foto pesa más de 3MB — comprimila o tomá otra.';
        return;
    }
    const reader = new FileReader();
    reader.onload = () => {
        emit('captured', reader.result);
        cerrar();
    };
    reader.readAsDataURL(f);
};

watch(() => props.open, (open) => { if (open) abrir(); }, { immediate: false });
onBeforeUnmount(cerrar);
</script>

<template>
    <div v-if="open" role="dialog" aria-modal="true"
         class="fixed inset-0 z-50 bg-black/85 flex items-center justify-center p-4"
         @click.self="cerrar" @keydown.escape="cerrar">
        <div class="bg-surface-900 border border-brand-500/40 rounded-2xl p-5 w-full max-w-lg">
            <div class="flex items-center justify-between mb-3">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-500">
                    📸 Foto del paquete cerrado
                </div>
                <button @click="cerrar" class="text-surface-400 hover:text-white"><X class="h-5 w-5"/></button>
            </div>

            <div v-if="!error" class="relative">
                <video ref="videoEl" autoplay playsinline muted
                       class="w-full rounded-lg bg-black aspect-[4/3] object-cover"></video>
                <canvas ref="canvasEl" class="hidden"></canvas>
                <div v-if="!ready" class="absolute inset-0 flex items-center justify-center text-white text-sm">
                    Iniciando cámara…
                </div>
            </div>

            <div v-if="error" class="p-3 bg-red-500/10 border-l-4 border-red-500 rounded text-red-300 text-sm mt-2">
                <div class="font-semibold mb-2">No pudimos acceder a la cámara.</div>
                <div class="opacity-85 mb-3 text-xs">{{ error }}</div>
                <label class="btn-primary cursor-pointer">
                    <Upload class="h-4 w-4"/> Subir foto desde archivo
                    <input ref="fileInput" type="file" accept="image/*" capture="environment" class="hidden" @change="subirArchivo"/>
                </label>
            </div>

            <div class="flex gap-2 mt-4">
                <button @click="cerrar" class="btn-secondary flex-1">Cancelar</button>
                <button v-if="!error" @click="capturar" :disabled="!ready" class="btn-primary flex-[2] py-3">
                    <Camera class="h-4 w-4"/> CAPTURAR
                </button>
            </div>
        </div>
    </div>
</template>
