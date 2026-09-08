<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeftRight, Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ traslados: { type: Object, required: true } });

const modal = ref(false);
const form = reactive({ origen_id: '', destino_id: '', motivo: '', observaciones: '' });
const procesando = ref(false);
const crear = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post('/app/inventario/traslados', form, {
        preserveScroll: true,
        onSuccess: () => { modal.value = false; Object.assign(form, { origen_id: '', destino_id: '', motivo: '', observaciones: '' }); },
        onFinish: () => procesando.value = false,
    });
};
</script>

<template>
    <Head title="Traslados de inventario"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between">
                <h1 class="text-2xl font-bold flex items-center gap-2"><ArrowLeftRight class="h-6 w-6 text-brand-600"/>Traslados entre ubicaciones</h1>
                <button @click="modal = true" class="btn-primary"><Plus class="h-4 w-4"/> Nuevo traslado</button>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">Número</th>
                            <th class="text-left p-3">Origen → Destino</th>
                            <th class="text-left p-3">Motivo</th>
                            <th class="text-left p-3">Solicitante</th>
                            <th class="text-left p-3">Fecha</th>
                            <th class="text-center p-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="t in traslados.data" :key="t.id" class="hover:bg-surface-50">
                            <td class="p-3 font-mono font-bold">{{ t.numero }}</td>
                            <td class="p-3 font-mono text-xs">{{ t.origen }} → {{ t.destino }}</td>
                            <td class="p-3">{{ t.motivo }}</td>
                            <td class="p-3">{{ t.solicitante }}</td>
                            <td class="p-3 text-xs">{{ t.fecha }}</td>
                            <td class="p-3 text-center">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-100 text-blue-800">{{ t.estado }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modal" @click.self="modal = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Nuevo traslado</h3>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="text-xs font-semibold">Origen (ID)</label><input type="number" v-model.number="form.origen_id" class="input w-full" autofocus/></div>
                        <div><label class="text-xs font-semibold">Destino (ID)</label><input type="number" v-model.number="form.destino_id" class="input w-full"/></div>
                    </div>
                    <p class="text-xs text-surface-500">Ver IDs en <a href="/app/dropi/ubicaciones" class="text-brand-600 hover:underline">Ubicaciones</a></p>
                    <div><label class="text-xs font-semibold">Motivo</label><input v-model="form.motivo" class="input w-full" placeholder="Ej: reponer stock venta"/></div>
                    <div><label class="text-xs font-semibold">Observaciones</label><textarea v-model="form.observaciones" rows="2" class="input w-full"></textarea></div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modal = false" class="btn-ghost">Cancelar</button>
                    <button @click="crear" :disabled="procesando" class="btn-primary">{{ procesando ? '…' : 'Crear' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
