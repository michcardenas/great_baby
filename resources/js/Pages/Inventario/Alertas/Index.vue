<script setup>
import { Head } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ alertas: { type: Object, required: true } });
</script>

<template>
    <Head title="Alertas de stock"/>
    <AppLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2"><Bell class="h-6 w-6 text-brand-600"/>Configuración de alertas de stock</h1>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">SKU</th>
                            <th class="text-left p-3">Producto</th>
                            <th class="text-left p-3">Ubicación</th>
                            <th class="text-right p-3">Mínimo</th>
                            <th class="text-right p-3">Punto reorden</th>
                            <th class="text-right p-3">Cant. reorden</th>
                            <th class="text-center p-3">Activa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="a in alertas.data" :key="a.id" class="hover:bg-surface-50">
                            <td class="p-3 font-mono text-xs">{{ a.sku }}</td>
                            <td class="p-3">{{ a.producto }}</td>
                            <td class="p-3 font-mono text-xs">{{ a.ubicacion }}</td>
                            <td class="p-3 text-right font-bold text-red-600">{{ a.minimo }}</td>
                            <td class="p-3 text-right font-bold text-amber-600">{{ a.reorden }}</td>
                            <td class="p-3 text-right">{{ a.cantidad_reorden }}</td>
                            <td class="p-3 text-center">{{ a.activa ? '✓' : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
