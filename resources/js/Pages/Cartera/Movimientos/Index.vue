<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ movimientos: { type: Object, required: true }, filtros: { type: Object, required: true } });

const desde = ref(props.filtros.desde);
const hasta = ref(props.filtros.hasta);
const cuenta = ref(props.filtros.cuenta || '');
const filtrar = () => router.get('/app/cartera/movimientos', { desde: desde.value, hasta: hasta.value, cuenta: cuenta.value || null }, { preserveState: true });

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });

const totalDebe = computed(() => props.movimientos.data.reduce((s, m) => s + Number(m.debe), 0));
const totalHaber = computed(() => props.movimientos.data.reduce((s, m) => s + Number(m.haber), 0));
</script>

<template>
    <Head title="Movimientos contables"/>
    <AppLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2"><BookOpen class="h-6 w-6 text-brand-600"/>Movimientos contables</h1>

            <div class="card p-3 flex items-center gap-2 flex-wrap">
                <div><label class="text-xs">Desde</label><input type="date" v-model="desde" class="input"/></div>
                <div><label class="text-xs">Hasta</label><input type="date" v-model="hasta" class="input"/></div>
                <div class="flex-1"><label class="text-xs">Cuenta PUC (prefijo)</label><input v-model="cuenta" placeholder="1305, 4135, 2408..." class="input w-full font-mono"/></div>
                <button @click="filtrar" class="btn-primary self-end">Filtrar</button>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="card p-3"><div class="text-xs">Total débito</div><div class="text-xl font-bold">{{ money(totalDebe) }}</div></div>
                <div class="card p-3"><div class="text-xs">Total crédito</div><div class="text-xl font-bold">{{ money(totalHaber) }}</div></div>
                <div class="card p-3" :class="Math.abs(totalDebe - totalHaber) > 0.01 ? 'ring-2 ring-red-500' : ''">
                    <div class="text-xs">Diferencia</div>
                    <div class="text-xl font-bold" :class="Math.abs(totalDebe - totalHaber) > 0.01 ? 'text-red-600' : 'text-emerald-600'">{{ money(totalDebe - totalHaber) }}</div>
                </div>
            </div>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-2">Fecha</th>
                            <th class="text-left p-2">Cuenta</th>
                            <th class="text-right p-2">Débito</th>
                            <th class="text-right p-2">Crédito</th>
                            <th class="text-left p-2">Descripción</th>
                            <th class="text-left p-2">Origen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="m in movimientos.data" :key="m.id" class="hover:bg-surface-50">
                            <td class="p-2 text-xs">{{ m.fecha }}</td>
                            <td class="p-2 font-mono font-bold">{{ m.cuenta }}</td>
                            <td class="p-2 text-right" :class="m.debe > 0 ? 'text-emerald-600 font-bold' : 'text-surface-300'">{{ m.debe > 0 ? money(m.debe) : '—' }}</td>
                            <td class="p-2 text-right" :class="m.haber > 0 ? 'text-red-600 font-bold' : 'text-surface-300'">{{ m.haber > 0 ? money(m.haber) : '—' }}</td>
                            <td class="p-2 text-xs">{{ m.descripcion }}</td>
                            <td class="p-2 text-xs font-mono">{{ m.origen }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
