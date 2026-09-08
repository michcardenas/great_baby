<script setup>
import { Head } from '@inertiajs/vue3';
import { Inbox } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
defineProps({ lotes: { type: Array, required: true } });
const badge = (e) => ({ completada: 'bg-emerald-100 text-emerald-800', procesando: 'bg-blue-100 text-blue-800', fallida: 'bg-red-100 text-red-800', pendiente: 'bg-amber-100 text-amber-800' }[e] || 'bg-surface-100');
</script>

<template>
    <Head title="Bandeja de importaciones"/>
    <AppLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2"><Inbox class="h-6 w-6 text-brand-600"/>Bandeja de importaciones</h1>
            <p class="text-sm text-surface-500">Histórico de cargas Excel/CSV (facturas, pagos, contactos, productos).</p>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">Tipo</th>
                            <th class="text-left p-3">Archivo</th>
                            <th class="text-center p-3">Estado</th>
                            <th class="text-right p-3">Filas</th>
                            <th class="text-right p-3">Éxito</th>
                            <th class="text-right p-3">Fallo</th>
                            <th class="text-right p-3">%</th>
                            <th class="text-left p-3">Usuario</th>
                            <th class="text-left p-3">Iniciada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="l in lotes" :key="l.id" class="hover:bg-surface-50">
                            <td class="p-3 text-xs uppercase font-bold">{{ l.tipo }}</td>
                            <td class="p-3 text-xs font-mono truncate max-w-xs">{{ l.archivo }}</td>
                            <td class="p-3 text-center"><span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(l.estado)]">{{ l.estado }}</span></td>
                            <td class="p-3 text-right">{{ l.total_filas }}</td>
                            <td class="p-3 text-right text-emerald-600">{{ l.exitosas }}</td>
                            <td class="p-3 text-right" :class="l.fallidas > 0 ? 'text-red-600 font-bold' : ''">{{ l.fallidas }}</td>
                            <td class="p-3 text-right font-bold">{{ l.porcentaje }}%</td>
                            <td class="p-3 text-xs">{{ l.usuario }}</td>
                            <td class="p-3 text-xs">{{ l.iniciada }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
