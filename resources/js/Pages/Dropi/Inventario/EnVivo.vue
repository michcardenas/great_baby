<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Boxes, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    filtros: { type: Object, required: true },
    variantes: { type: Array, required: true },
    ubicaciones: { type: Array, required: true },
    categorias: { type: Array, required: true },
});

const q = ref(props.filtros.q || '');
const cat = ref(props.filtros.categoria || '');

let deb;
const buscar = () => {
    clearTimeout(deb);
    deb = setTimeout(() => {
        router.get('/app/dropi/inventario-en-vivo', { q: q.value, categoria: cat.value || null }, { preserveState: true, replace: true });
    }, 300);
};
</script>

<template>
    <Head title="Inventario en vivo"/>
    <AppLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <Boxes class="h-6 w-6 text-brand-600"/>
                Inventario en vivo — variantes × ubicaciones
            </h1>

            <div class="card p-3 flex items-center gap-2 flex-wrap">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-500"/>
                    <input v-model="q" @input="buscar" placeholder="Buscar por SKU, referencia o nombre…" class="input w-full pl-10"/>
                </div>
                <select v-model="cat" @change="buscar" class="input">
                    <option value="">Todas las categorías</option>
                    <option v-for="c in categorias" :key="c" :value="c">{{ c }}</option>
                </select>
            </div>

            <div class="card overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="text-[10px] uppercase text-surface-500 border-b sticky top-0 bg-white dark:bg-surface-900 z-10">
                        <tr>
                            <th class="text-left p-2 min-w-[200px]">Variante</th>
                            <th v-for="u in ubicaciones" :key="u.id" class="text-center p-2 min-w-[60px]" :title="u.nombre">
                                {{ u.codigo }}
                            </th>
                            <th class="text-right p-2 font-bold">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="v in variantes" :key="v.id" class="hover:bg-surface-50">
                            <td class="p-2">
                                <div class="font-mono text-[10px] text-surface-500">{{ v.codigo }}</div>
                                <div class="font-medium">{{ v.producto }}</div>
                                <div class="text-[10px] text-surface-500">{{ v.referencia }} · {{ v.detalle }}</div>
                            </td>
                            <td v-for="u in ubicaciones" :key="u.id" class="p-2 text-center"
                                :class="{'font-bold text-brand-600': v.saldos[u.id] > 0, 'text-surface-300': !v.saldos[u.id]}">
                                {{ v.saldos[u.id] ?? '·' }}
                            </td>
                            <td class="p-2 text-right font-bold" :class="v.total > 0 ? 'text-emerald-600' : 'text-surface-400'">
                                {{ v.total }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-surface-500">Máximo 200 variantes por página · Total = suma de todos los saldos.</p>
        </div>
    </AppLayout>
</template>
