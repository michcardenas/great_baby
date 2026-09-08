<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { useTransition, TransitionPresets } from '@vueuse/core';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    subtitle: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
    color: { type: String, default: 'gray' }, // gray|amber|emerald|blue|red|purple
    format: { type: String, default: 'number' }, // number|money|time
    delta: { type: Number, default: null },
    deltaLabel: { type: String, default: '' },
    animated: { type: Boolean, default: true },
});

// Animación SOLO en la carga inicial. Después de eso, los cambios de props
// (auto-refresh cada 30s) actualizan el valor mostrado sin re-animar desde 0.
const yaAnimo = ref(false);
const source = ref(0);
const output = props.animated
    ? useTransition(source, { duration: 800, transition: TransitionPresets.easeOutCubic })
    : source;

onMounted(() => {
    source.value = Number(props.value) || 0;
    setTimeout(() => { yaAnimo.value = true; }, 850);
});
watch(() => props.value, (v) => { source.value = Number(v) || 0; });

const displayValue = computed(() => {
    // Antes de la primera animación: mostrar `output` (que va de 0 al valor)
    // Después: mostrar el valor directo de props (sin re-animar)
    const v = (props.animated && !yaAnimo.value) ? output.value : Number(props.value);
    if (props.format === 'money') return '$' + Math.round(v).toLocaleString('es-CO');
    if (props.format === 'time') return v > 0 ? formatTime(Math.round(v)) : '—';
    return Math.round(v).toLocaleString('es-CO');
});

const formatTime = (s) => {
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
};

const colorClass = computed(() => ({
    gray:    { border: 'border-l-slate-400',   text: 'text-slate-700 dark:text-slate-300',   iconBg: 'text-slate-400/40' },
    amber:   { border: 'border-l-brand-500',   text: 'text-brand-600 dark:text-brand-400',   iconBg: 'text-brand-500/40' },
    emerald: { border: 'border-l-emerald-500', text: 'text-emerald-600 dark:text-emerald-400', iconBg: 'text-emerald-500/40' },
    blue:    { border: 'border-l-blue-500',    text: 'text-blue-600 dark:text-blue-400',     iconBg: 'text-blue-500/40' },
    red:     { border: 'border-l-red-500',     text: 'text-red-600 dark:text-red-400',       iconBg: 'text-red-500/40' },
    purple:  { border: 'border-l-purple-500',  text: 'text-purple-600 dark:text-purple-400', iconBg: 'text-purple-500/40' },
}[props.color] || {}));

const deltaSign = computed(() => (props.delta ?? 0) > 0 ? '↑' : (props.delta ?? 0) < 0 ? '↓' : '→');
const deltaColor = computed(() => (props.delta ?? 0) > 0 ? 'text-emerald-600' : (props.delta ?? 0) < 0 ? 'text-red-600' : 'text-slate-500');
</script>

<template>
    <div :class="['card p-5 border-l-4 transition hover:shadow-md', colorClass.border]">
        <div class="flex items-center justify-between">
            <div class="min-w-0">
                <div class="text-xs uppercase tracking-wider text-surface-500 font-medium leading-snug">
                    {{ label }}
                </div>
                <div :class="['text-3xl font-black mt-1 tabular-nums', colorClass.text]">
                    {{ displayValue }}
                </div>
                <div v-if="subtitle" class="text-xs text-surface-500 mt-1">{{ subtitle }}</div>
                <div v-if="delta !== null" class="text-xs mt-1 font-semibold" :class="deltaColor">
                    {{ deltaSign }} {{ Math.abs(delta) }}% <span class="text-surface-500 font-normal">{{ deltaLabel }}</span>
                </div>
            </div>
            <component v-if="icon" :is="icon" :class="['h-10 w-10 flex-shrink-0', colorClass.iconBg]"/>
        </div>
    </div>
</template>
