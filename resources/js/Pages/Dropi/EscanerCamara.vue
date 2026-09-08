<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Camera, Search, CheckCircle, XCircle, Keyboard } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const codigo = ref('');
const resultado = ref(null);
const buscando = ref(false);
const streamActivo = ref(false);
const videoEl = ref(null);
const errorMsg = ref('');

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });

const buscar = async () => {
    if (!codigo.value.trim() || buscando.value) return;
    buscando.value = true;
    errorMsg.value = '';
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch('/app/dropi/escaner/buscar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ codigo: codigo.value.trim() }),
        });
        resultado.value = await res.json();
    } catch (e) {
        errorMsg.value = 'Error de red';
    } finally {
        buscando.value = false;
    }
};

let stream;
const iniciarCamara = async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        if (videoEl.value) {
            videoEl.value.srcObject = stream;
            await videoEl.value.play();
        }
        streamActivo.value = true;
    } catch (e) {
        errorMsg.value = 'No se pudo acceder a la cámara: ' + e.message;
    }
};
const detenerCamara = () => {
    stream?.getTracks().forEach(t => t.stop());
    streamActivo.value = false;
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
            <p class="text-sm text-surface-500">Fallback cuando no hay pistola física. Enfoca el código de barras o la guía, o escríbela abajo.</p>

            <div v-if="errorMsg" class="p-3 rounded-lg bg-red-50 border-l-4 border-red-500 text-red-700 text-sm">
                {{ errorMsg }}
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2 flex items-center gap-2">
                    <Camera class="h-3 w-3"/> Cámara
                </div>
                <button v-if="!streamActivo" @click="iniciarCamara" class="btn-primary">Activar cámara</button>
                <div v-else class="space-y-2">
                    <video ref="videoEl" class="w-full max-h-96 bg-black rounded" autoplay playsinline muted></video>
                    <button @click="detenerCamara" class="btn-ghost text-red-600">Detener cámara</button>
                    <p class="text-xs text-surface-500">Enfoca el código, luego escríbelo manualmente en el input de abajo.</p>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2 flex items-center gap-2">
                    <Keyboard class="h-3 w-3"/> Buscar guía o Dropi ID
                </div>
                <div class="flex gap-2">
                    <input v-model="codigo" @keydown.enter="buscar" autofocus
                        class="input flex-1 font-mono text-lg" placeholder="4200000..."/>
                    <button @click="buscar" :disabled="buscando" class="btn-primary">
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
                        <div><b>Estado:</b> {{ resultado.pedido.estado }}</div>
                        <div><b>Cliente:</b> {{ resultado.pedido.cliente }}</div>
                        <div><b>Ciudad:</b> {{ resultado.pedido.ciudad }}</div>
                        <div><b>Monto:</b> {{ money(resultado.pedido.monto) }}</div>
                    </div>
                    <div class="mt-4">
                        <div class="text-xs uppercase font-bold text-brand-600 mb-1">Ítems ({{ resultado.pedido.items.length }})</div>
                        <ul class="text-sm">
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
