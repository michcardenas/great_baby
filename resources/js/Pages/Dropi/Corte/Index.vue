<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Scissors, Plus, Lock, FileText } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    cortes: { type: Object, required: true },
});

const modalNuevo = ref(false);
const form = reactive({ numero: '', fecha: new Date().toISOString().slice(0, 10) });
const procesando = ref(false);

const crear = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post('/app/dropi/cortes', form, {
        preserveScroll: true,
        onSuccess: () => { modalNuevo.value = false; form.numero = ''; },
        onFinish: () => procesando.value = false,
    });
};

const cerrar = (id, numero) => {
    if (!confirm(`Cerrar corte ${numero}? Después no se pueden agregar más pedidos.`)) return;
    router.post(`/app/dropi/cortes/${id}/cerrar`, {}, { preserveScroll: true });
};

const badge = (e) => ({
    abierto: 'bg-blue-100 text-blue-800',
    cerrado: 'bg-emerald-100 text-emerald-800',
    liquidado: 'bg-brand-100 text-brand-800',
}[e] || 'bg-surface-100');
</script>

<template>
    <Head title="Cortes Dropi"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Scissors class="h-6 w-6 text-brand-600"/>
                    Cortes Dropi
                </h1>
                <button @click="modalNuevo = true" class="btn-primary"><Plus class="h-4 w-4"/> Nuevo corte</button>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">Número</th>
                            <th class="text-left p-3">Fecha</th>
                            <th class="text-center p-3">Estado</th>
                            <th class="text-right p-3">Pedidos</th>
                            <th class="text-left p-3">Cerrado</th>
                            <th class="text-right p-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="c in cortes.data" :key="c.id" class="hover:bg-surface-50">
                            <td class="p-3 font-bold">{{ c.numero }}</td>
                            <td class="p-3">{{ c.fecha }}</td>
                            <td class="p-3 text-center">
                                <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(c.estado)]">{{ c.estado }}</span>
                            </td>
                            <td class="p-3 text-right">{{ c.pedidos_count }}</td>
                            <td class="p-3 text-xs">{{ c.cerrado_at ?? '—' }}</td>
                            <td class="p-3 text-right">
                                <button v-if="c.estado === 'abierto'" @click="cerrar(c.id, c.numero)"
                                    class="btn-ghost text-xs text-red-600">
                                    <Lock class="h-3 w-3"/> Cerrar
                                </button>
                                <a v-if="c.estado !== 'abierto'" :href="`/dropi/manifiesto/${c.id}`"
                                    class="btn-ghost text-xs" target="_blank">
                                    <FileText class="h-3 w-3"/> Manifiesto
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modalNuevo" @click.self="modalNuevo = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Nuevo corte</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-semibold">Número</label>
                        <input v-model.number="form.numero" type="number" min="1" class="input w-full" autofocus/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Fecha</label>
                        <input v-model="form.fecha" type="date" class="input w-full"/>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <button @click="modalNuevo = false" class="btn-ghost">Cancelar</button>
                    <button @click="crear" :disabled="procesando" class="btn-primary">{{ procesando ? 'Creando…' : 'Crear' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
