<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Cloud } from 'lucide-vue-next';
import axios from 'axios';

const estado = ref(null);
let timer = null;

const cargar = async () => {
    try {
        const { data } = await axios.get('/app/siigo/semaforo');
        estado.value = data;
    } catch {
        // Silencioso · no bloquear header si falla.
    }
};

const colorClases = computed(() => {
    const c = estado.value?.color || 'surface';
    return {
        emerald: 'bg-emerald-500',
        amber: 'bg-amber-500 animate-pulse',
        red: 'bg-red-500 animate-pulse',
        surface: 'bg-surface-300',
    }[c];
});

const titulo = computed(() => {
    if (! estado.value) return 'SIIGO · cargando…';
    return `SIIGO · ${estado.value.label} · ${estado.value.pendientes} en cola · ${estado.value.errores24h} errores 24h`;
});

onMounted(() => {
    cargar();
    timer = setInterval(cargar, 30000); // 30s
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <Link href="/app/siigo"
          class="relative inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-surface-200 hover:bg-surface-50 text-xs"
          :title="titulo">
        <Cloud class="h-4 w-4 text-surface-600"/>
        <span class="hidden md:inline text-surface-600">SIIGO</span>
        <span class="relative flex h-2.5 w-2.5">
            <span :class="['relative inline-flex rounded-full h-2.5 w-2.5', colorClases]"></span>
        </span>
    </Link>
</template>
