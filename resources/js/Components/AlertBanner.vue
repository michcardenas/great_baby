<script setup>
import { ref, watch } from 'vue';
import { AlertTriangle, X } from 'lucide-vue-next';
import { useSonido } from '@/composables/useSonido';

const props = defineProps({
    alertas: { type: Array, required: true }, // [{id, nivel, mensaje}]
});

const { beep } = useSonido();

// Snooze por ID persistido en sessionStorage (F6 auditor: sin dismiss ni snooze).
const snoozedIds = ref(new Set(readSnooze()));
function readSnooze() {
    try {
        const raw = sessionStorage.getItem('gb.alertas.snooze');
        return raw ? JSON.parse(raw) : [];
    } catch (e) { return []; }
}
function writeSnooze() {
    try { sessionStorage.setItem('gb.alertas.snooze', JSON.stringify([...snoozedIds.value])); } catch (e) {}
}
const dismiss = (id) => {
    snoozedIds.value.add(id);
    writeSnooze();
};
const visibles = () => props.alertas.filter(a => ! snoozedIds.value.has(a.id));

// Beep SOLO cuando aparecen alertas nuevas después del primer render.
// Antes tenía `immediate: true` → sonaba en cada montaje (login, back al Dashboard).
watch(() => visibles().map(a => a.id).join('|'), (nuevo, viejo) => {
    // viejo === undefined en el primer disparo — solo activo si es transición REAL.
    if (viejo === undefined) return;
    if (nuevo && nuevo !== viejo && visibles().length > 0) {
        beep('alert');
    }
});
</script>

<template>
    <div v-if="visibles().length" class="rounded-xl border-l-4 border-l-red-500 bg-red-50 dark:bg-red-950/30 p-4 space-y-2 animate-pulse-soft">
        <div v-for="a in visibles()" :key="a.id" class="flex items-center gap-3 text-red-800 dark:text-red-200 font-semibold">
            <AlertTriangle class="h-5 w-5 flex-shrink-0"/>
            <span class="flex-1">{{ a.mensaje }}</span>
            <button @click="dismiss(a.id)" class="text-red-500/70 hover:text-red-700 dark:hover:text-red-100" :aria-label="'Ocultar alerta ' + a.id" title="Ocultar hasta próxima sesión">
                <X class="h-4 w-4"/>
            </button>
        </div>
    </div>
</template>
