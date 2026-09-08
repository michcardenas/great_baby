<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    filas: { type: Array, required: true }, // [{uid, nombre, total, prom}]
});

const userId = computed(() => usePage().props.auth?.user?.id);

const medalla = (i) => ['🥇', '🥈', '🥉'][i] ?? String(i + 1);
const fmtTime = (s) => {
    if (!s) return '—';
    const m = Math.floor(s / 60);
    return `${String(m).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
};
</script>

<template>
    <div>
        <div v-if="!filas.length" class="text-center py-8 text-surface-500">
            <div class="text-3xl">📦</div>
            <div class="text-sm mt-2">Aún no hay empaques hoy</div>
        </div>
        <div v-else class="overflow-x-auto"><table class="w-full min-w-[420px] text-sm">
            <thead>
                <tr class="text-surface-500 dark:text-surface-400 text-xs uppercase border-b border-surface-200 dark:border-surface-800">
                    <th class="text-left py-2 w-10">#</th>
                    <th class="text-left">Operario</th>
                    <th class="text-right">Empacados</th>
                    <th class="text-right">Prom</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(r, i) in filas" :key="r.uid" class="border-b border-surface-100 dark:border-surface-900">
                    <td class="py-2.5 font-black text-xl" :class="i === 0 ? 'text-brand-500' : 'text-surface-400'">
                        {{ medalla(i) }}
                    </td>
                    <td :class="['font-medium', r.uid === userId ? 'text-brand-600 font-bold' : '']">
                        {{ r.nombre }}
                        <span v-if="r.uid === userId" class="text-xs text-surface-500 font-normal">(tú)</span>
                    </td>
                    <td class="text-right font-bold text-emerald-600">{{ r.total }}</td>
                    <td class="text-right text-surface-500 font-mono">{{ fmtTime(r.prom) }}</td>
                </tr>
            </tbody>
        </table></div>
    </div>
</template>
