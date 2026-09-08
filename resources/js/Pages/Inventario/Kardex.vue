<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { History, Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    codigo: { type: String, default: '' },
    variante: { type: Object, default: null },
    movimientos: { type: Array, required: true },
    saldoTotal: { type: Number, default: 0 },
});

const q = ref(props.codigo);
const buscar = () => router.get('/app/inventario/kardex', { codigo: q.value }, { preserveState: false });
</script>

<template>
    <Head title="Kardex por variante"/>
    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <History class="h-6 w-6 text-brand-600"/>
                Kardex por variante
            </h1>

            <div class="card p-4">
                <label class="text-xs font-semibold">Código de barras o referencia</label>
                <div class="flex gap-2">
                    <input v-model="q" @keydown.enter="buscar" autofocus class="input flex-1 font-mono"/>
                    <button @click="buscar" class="btn-primary"><Search class="h-4 w-4"/> Buscar</button>
                </div>
            </div>

            <div v-if="codigo && !variante" class="card p-8 text-center text-red-600">Variante no encontrada</div>

            <div v-if="variante" class="card p-4 flex items-start justify-between">
                <div>
                    <div class="font-mono text-xs text-surface-500">{{ variante.codigo }}</div>
                    <div class="font-bold text-lg">{{ variante.producto }}</div>
                    <div class="text-sm text-surface-500">{{ variante.referencia }} · {{ variante.detalle }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-surface-500">Saldo total actual</div>
                    <div class="text-3xl font-bold" :class="saldoTotal > 0 ? 'text-emerald-600' : 'text-surface-400'">{{ saldoTotal }}</div>
                </div>
            </div>

            <div v-if="variante" class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-[10px] uppercase text-surface-500 border-b">
                        <tr>
                            <th class="text-left p-2">Fecha</th>
                            <th class="text-left p-2">Tipo</th>
                            <th class="text-left p-2">Ubicación</th>
                            <th class="text-right p-2">Cantidad</th>
                            <th class="text-right p-2">Saldo</th>
                            <th class="text-left p-2">Referencia</th>
                            <th class="text-left p-2">Notas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="m in movimientos" :key="m.id" class="hover:bg-surface-50">
                            <td class="p-2 text-xs">{{ m.fecha }}</td>
                            <td class="p-2 text-xs uppercase">{{ m.tipo }}</td>
                            <td class="p-2 text-xs">{{ m.ubicacion }}</td>
                            <td class="p-2 text-right font-bold" :class="m.cantidad >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                {{ m.cantidad > 0 ? '+' : '' }}{{ m.cantidad }}
                            </td>
                            <td class="p-2 text-right font-bold">{{ m.saldo }}</td>
                            <td class="p-2 text-xs font-mono">{{ m.referencia }}</td>
                            <td class="p-2 text-xs text-surface-500">{{ m.notas || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
