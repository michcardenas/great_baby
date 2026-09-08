<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';

const abierto = ref(false);
const eventos = ref([]);
const noLeidas = ref(0);
let ultimoCheck = new Date().toISOString();
let interval;
let audioCtx;

const beep = () => {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        const o = audioCtx.createOscillator(), g = audioCtx.createGain();
        o.type = 'sine'; o.frequency.value = 880;
        g.gain.setValueAtTime(0.05, audioCtx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.15);
        o.connect(g); g.connect(audioCtx.destination);
        o.start(); o.stop(audioCtx.currentTime + 0.15);
    } catch {}
};

const poll = async () => {
    try {
        const res = await fetch('/app/notificaciones?desde=' + encodeURIComponent(ultimoCheck), {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (data.eventos.length > 0) {
            eventos.value = [...data.eventos, ...eventos.value].slice(0, 30);
            noLeidas.value += data.eventos.length;
            beep();
        }
        ultimoCheck = data.ahora;
    } catch {}
};

const abrir = () => {
    abierto.value = !abierto.value;
    if (abierto.value) noLeidas.value = 0;
};
const irA = (e) => { abierto.value = false; router.visit(e.url); };

onMounted(() => { poll(); interval = setInterval(poll, 20000); });
onBeforeUnmount(() => interval && clearInterval(interval));

const colorClass = (c) => ({
    blue: 'border-blue-500', emerald: 'border-emerald-500',
    amber: 'border-amber-500', brand: 'border-brand-500',
    red: 'border-red-500',
}[c] || 'border-surface-300');
</script>

<template>
    <div class="relative">
        <button @click="abrir" class="btn-ghost p-2 relative" aria-label="Notificaciones" title="Notificaciones">
            <Bell class="h-5 w-5" :class="noLeidas > 0 ? 'text-red-600 animate-pulse' : ''"/>
            <span v-if="noLeidas > 0" class="absolute -top-0.5 -right-0.5 h-4 min-w-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold flex items-center justify-center">
                {{ noLeidas > 9 ? '9+' : noLeidas }}
            </span>
        </button>
        <div v-if="abierto" @click.self="abierto = false" class="fixed inset-0 z-40" style="background: transparent;">
            <div class="absolute right-4 top-16 w-80 bg-white dark:bg-surface-900 border border-surface-200 dark:border-surface-800 rounded-xl shadow-2xl overflow-hidden">
                <div class="p-3 border-b border-surface-200 dark:border-surface-800 font-bold text-sm">
                    Notificaciones ({{ eventos.length }})
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <div v-if="!eventos.length" class="p-6 text-center text-sm text-surface-500">Sin novedades</div>
                    <button v-for="e in eventos" :key="e.id" @click="irA(e)"
                        :class="['w-full text-left p-3 hover:bg-surface-50 dark:hover:bg-surface-800 border-l-4', colorClass(e.color)]">
                        <div class="flex items-start gap-2">
                            <span class="text-xl">{{ e.icon }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-sm truncate">{{ e.titulo }}</div>
                                <div class="text-xs text-surface-500 truncate">{{ e.body }}</div>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
