<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { FileSearch } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
const props = defineProps({ tipo: String, id: Number, movimientos: Array, totales: Object });
const tipo = ref(props.tipo);
const id = ref(props.id);
const filtrar = () => router.get('/app/contabilidad/reporte-detalle', { tipo: tipo.value, id: id.value });
const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
</script>

<template>
    <Head title="Reporte detalle asiento"/>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2"><FileSearch class="h-6 w-6 text-brand-600"/>Detalle del asiento contable</h1>

            <div class="card p-3 flex items-center gap-2">
                <select v-model="tipo" class="input"><option value="factura">Factura</option><option value="pago">Pago</option></select>
                <input type="number" v-model.number="id" placeholder="ID" class="input flex-1"/>
                <button @click="filtrar" class="btn-primary">Consultar</button>
            </div>

            <div v-if="movimientos.length" class="card p-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-surface-500 border-b">
                            <tr><th class="text-left p-2">Fecha</th><th class="text-left p-2">Cuenta</th><th class="text-left p-2">Descripción</th><th class="text-right p-2">Débito</th><th class="text-right p-2">Crédito</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="m in movimientos" :key="m.id">
                                <td class="p-2 text-xs">{{ m.fecha }}</td>
                                <td class="p-2 font-mono font-bold">{{ m.cuenta }}</td>
                                <td class="p-2 text-xs">{{ m.descripcion }}</td>
                                <td class="p-2 text-right text-emerald-600">{{ m.debe > 0 ? money(m.debe) : '—' }}</td>
                                <td class="p-2 text-right text-red-600">{{ m.haber > 0 ? money(m.haber) : '—' }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t font-bold">
                            <tr>
                                <td colspan="3" class="p-2 text-right">Totales</td>
                                <td class="p-2 text-right text-emerald-600">{{ money(totales.debe) }}</td>
                                <td class="p-2 text-right text-red-600">{{ money(totales.haber) }}</td>
                            </tr>
                            <tr :class="Math.abs(totales.diff) > 0.01 ? 'text-red-600' : 'text-emerald-600'">
                                <td colspan="4" class="p-2 text-right">Diferencia (debe - haber)</td>
                                <td class="p-2 text-right">{{ money(totales.diff) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div v-else class="card p-8 text-center text-surface-500 text-sm">Ingresa tipo e ID para ver el asiento contable.</div>
        </div>
    </AppLayout>
</template>
