<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Trash2, RotateCcw, ArrowLeft, Package } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppConfirmModal from '@/Components/AppConfirmModal.vue';
import { useMoney } from '@/composables/useMoney';

const props = defineProps({
    papelera: { type: Array, required: true },
    total: { type: Number, required: true },
});

const { money } = useMoney();

// Sprint confirm → modal · reemplaza window.confirm bloqueado en iframe.
const modalConfirm = ref(null);
const restaurar = (p) => {
    modalConfirm.value = {
        titulo: `¿Restaurar ${p.referencia}?`,
        mensaje: 'Volverá al catálogo activo' + (p.tenia_siigo ? ' · también se re-activará en SIIGO.' : '.'),
        color: 'emerald',
        textoConfirmar: 'Restaurar',
        onConfirmar: () => {
            modalConfirm.value = null;
            router.post(`/app/catalogo/productos-papelera/${p.id}/restaurar`, {}, { preserveScroll: true });
        },
    };
};
</script>

<template>
    <Head title="Papelera · Productos"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <Link href="/app/catalogo/productos" class="text-sm text-brand-600 hover:underline inline-flex items-center gap-1">
                        <ArrowLeft class="h-4 w-4"/> Volver a Productos
                    </Link>
                    <h1 class="text-2xl font-bold flex items-center gap-2 mt-1">
                        <Trash2 class="h-6 w-6 text-amber-600"/>
                        Papelera de productos
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">
                        Últimos 30 días · {{ total }} productos eliminados · se purgan automáticamente después de 30 días
                    </p>
                </div>
            </div>

            <div v-if="papelera.length === 0" class="card p-10 text-center text-surface-500">
                <Package class="h-12 w-12 mx-auto text-surface-300 mb-2"/>
                Papelera vacía · no hay productos eliminados en los últimos 30 días.
            </div>

            <div v-else class="card overflow-x-auto">
                <table v-tabla-movil class="w-full text-sm">
                    <thead class="bg-surface-50 dark:bg-surface-800 text-left text-xs uppercase text-surface-500">
                        <tr>
                            <th class="p-3">Referencia</th>
                            <th class="p-3">Nombre</th>
                            <th class="p-3 text-right">Precio</th>
                            <th class="p-3">Eliminado hace</th>
                            <th class="p-3">SIIGO</th>
                            <th class="p-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in papelera" :key="p.id" class="border-t border-surface-100">
                            <td class="p-3 font-mono text-xs text-brand-600">{{ p.referencia }}</td>
                            <td class="p-3">{{ p.nombre }}</td>
                            <td class="p-3 text-right">{{ money(p.precio_proveedor) }}</td>
                            <td class="p-3 text-xs text-surface-500">{{ p.deleted_hace }}</td>
                            <td class="p-3">
                                <span v-if="p.tenia_siigo" class="text-xs text-emerald-600">Sí</span>
                                <span v-else class="text-xs text-surface-400">—</span>
                            </td>
                            <td class="p-3 text-right">
                                <button @click="restaurar(p)"
                                        class="text-xs inline-flex items-center gap-1 px-2 py-1 rounded border border-emerald-500 text-emerald-700 hover:bg-emerald-50">
                                    <RotateCcw class="h-3 w-3"/> Restaurar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <AppConfirmModal :cfg="modalConfirm" @cerrar="modalConfirm = null"/>
    </AppLayout>
</template>
