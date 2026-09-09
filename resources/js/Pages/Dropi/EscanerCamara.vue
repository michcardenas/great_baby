<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Camera, Search, CheckCircle, XCircle, Keyboard } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useMoney } from '@/composables/useMoney';
import { usePedidoBadge } from '@/composables/usePedidoBadge';

const { money } = useMoney();
const { badge } = usePedidoBadge();

const codigo = ref('');
const resultado = ref(null);
const buscando = ref(false);
const streamActivo = ref(false);
const videoEl = ref(null);
const canvasEl = ref(null);
const errorMsg = ref('');
const infoMsg = ref('');

// U2/U16 · detección real con BarcodeDetector (Chrome/Edge) + fallback claro.
const supportsBarcode = typeof window !== 'undefined' && 'BarcodeDetector' in window;
const supportsCamera = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;

const buscar = async (cod) => {
    const q = (cod ?? codigo.value ?? '').trim();
    if (!q || buscando.value) return;
    buscando.value = true;
    errorMsg.value = '';
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch('/app/dropi/escaner/buscar', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ codigo: q }),
        });
        resultado.value = await res.json();
    } catch (e) {
        errorMsg.value = 'Error de red';
    } finally {
        buscando.value = false;
    }
};

let stream;
let detector = null;
let detectHandle = null;
let ultimoDetectado = null;

const iniciarCamara = async () => {
    errorMsg.value = '';
    if (!supportsCamera) {
        errorMsg.value = 'Este navegador no soporta acceso a cámara. Usa la pistola o escribe la guía manualmente.';
        return;
    }
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        if (videoEl.value) {
            videoEl.value.srcObject = stream;
            await videoEl.value.play();
        }
        streamActivo.value = true;

        if (supportsBarcode) {
            detector = new window.BarcodeDetector({
                formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code', 'itf'],
            });
            infoMsg.value = 'Cámara lista — detectando…';
            iniciarDeteccion();
        } else {
            infoMsg.value = 'Cámara lista (sin decoder nativo). Escribe la guía manualmente.';
        }
    } catch (e) {
        errorMsg.value = 'No se pudo acceder a la cámara: ' + (e?.message || e);
    }
};

const iniciarDeteccion = () => {
    if (!detector || !videoEl.value) return;
    detectHandle = setInterval(async () => {
        if (!streamActivo.value || !videoEl.value) return;
        try {
            const barcodes = await detector.detect(videoEl.value);
            if (barcodes.length) {
                const raw = String(barcodes[0].rawValue || '').trim();
                if (raw && raw !== ultimoDetectado) {
                    ultimoDetectado = raw;
                    codigo.value = raw;
                    infoMsg.value = '✓ Detectado: ' + raw;
                    await buscar(raw);
                }
            }
        } catch { /* frame skip */ }
    }, 400);
};

const detenerCamara = () => {
    if (detectHandle) { clearInterval(detectHandle); detectHandle = null; }
    stream?.getTracks().forEach(t => t.stop());
    streamActivo.value = false;
    infoMsg.value = '';
};
onBeforeUnmount(detenerCamara);
</script>

<template>
    <Head title="Escáner cámara"/>
    <AppLayout>
        <div class="max-w-3xl mx-auto space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <Camera class="h-6 w-6 text-brand-600"/>
                Escáner con cámara
            </h1>
            <p class="text-sm text-surface-500 dark:text-surface-400">
                Fallback cuando no hay pistola física.
                <span v-if="supportsBarcode" class="text-emerald-600 font-semibold">Detección automática activa.</span>
                <span v-else class="text-amber-600 font-semibold">Este navegador no decodifica barras — escribe la guía manualmente.</span>
            </p>

            <div v-if="errorMsg" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/40 border-l-4 border-red-500 text-red-700 dark:text-red-300 text-sm">
                {{ errorMsg }}
            </div>

            <div v-if="supportsCamera" class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2 flex items-center gap-2">
                    <Camera class="h-3 w-3"/> Cámara
                </div>
                <button v-if="!streamActivo" @click="iniciarCamara" class="btn-primary">Activar cámara</button>
                <div v-else class="space-y-2">
                    <video ref="videoEl" class="w-full max-h-96 bg-black rounded" autoplay playsinline muted></video>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-emerald-600 dark:text-emerald-400" v-if="infoMsg">{{ infoMsg }}</span>
                        <button @click="detenerCamara" class="btn-ghost text-red-600">Detener cámara</button>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2 flex items-center gap-2">
                    <Keyboard class="h-3 w-3"/> Buscar guía o Dropi ID
                </div>
                <div class="flex gap-2">
                    <input v-model="codigo" @keydown.enter="buscar()" autofocus
                        class="input flex-1 font-mono text-lg" placeholder="4200000..."/>
                    <button @click="buscar()" :disabled="buscando" class="btn-primary disabled:opacity-50">
                        <Search class="h-4 w-4"/> {{ buscando ? '…' : 'Buscar' }}
                    </button>
                </div>
            </div>

            <div v-if="resultado" class="card p-5">
                <div v-if="!resultado.encontrado" class="text-center py-8">
                    <XCircle class="h-16 w-16 text-red-500 mx-auto mb-2"/>
                    <p class="font-bold text-lg">Pedido no encontrado</p>
                    <p class="text-sm text-surface-500">Verifica el código.</p>
                </div>
                <div v-else>
                    <div class="flex items-center gap-2 text-emerald-600 mb-3">
                        <CheckCircle class="h-6 w-6"/>
                        <span class="font-bold text-lg">Pedido encontrado</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><b>Guía:</b> <span class="font-mono">{{ resultado.pedido.guia }}</span></div>
                        <div><b>Estado:</b>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase ml-1" :class="badge(resultado.pedido.estado).cls">
                                {{ badge(resultado.pedido.estado).label }}
                            </span>
                        </div>
                        <div><b>Cliente:</b> {{ resultado.pedido.cliente }}</div>
                        <div><b>Ciudad:</b> {{ resultado.pedido.ciudad }}</div>
                        <div v-if="resultado.pedido.monto != null"><b>Monto:</b> {{ money(resultado.pedido.monto) }}</div>
                    </div>
                    <div class="mt-4">
                        <div class="text-xs uppercase font-bold text-brand-600 mb-1">Ítems ({{ resultado.pedido.items.length }})</div>
                        <ul class="text-sm divide-y divide-surface-100 dark:divide-surface-800">
                            <li v-for="(it, idx) in resultado.pedido.items" :key="idx" class="py-1 flex justify-between">
                                <span>{{ it.descripcion }}</span>
                                <span class="font-bold">×{{ it.cantidad }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
