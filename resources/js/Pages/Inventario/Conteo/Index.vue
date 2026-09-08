<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList, Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ tomas: { type: Object, required: true } });

const modal = ref(false);
const form = reactive({ ubicacion_id: '', tipo: 'ciclico', alcance: '', observaciones: '' });
const procesando = ref(false);
const crear = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post('/app/inventario/conteos', form, {
        preserveScroll: true,
        onSuccess: () => { modal.value = false; Object.assign(form, { ubicacion_id: '', alcance: '', observaciones: '' }); },
        onFinish: () => procesando.value = false,
    });
};
const badge = (e) => ({ abierta: 'bg-blue-100 text-blue-800', cerrada: 'bg-emerald-100 text-emerald-800' }[e] || 'bg-surface-100');
</script>

<template>
    <Head title="Conteos físicos"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between">
                <h1 class="text-2xl font-bold flex items-center gap-2"><ClipboardList class="h-6 w-6 text-brand-600"/>Conteos físicos</h1>
                <button @click="modal = true" class="btn-primary"><Plus class="h-4 w-4"/> Nuevo conteo</button>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">Número</th>
                            <th class="text-left p-3">Ubicación</th>
                            <th class="text-left p-3">Tipo</th>
                            <th class="text-left p-3">Creador</th>
                            <th class="text-left p-3">Fecha</th>
                            <th class="text-right p-3">Diferencias</th>
                            <th class="text-center p-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="t in tomas.data" :key="t.id" class="hover:bg-surface-50">
                            <td class="p-3 font-mono font-bold">{{ t.numero }}</td>
                            <td class="p-3">{{ t.ubicacion }}</td>
                            <td class="p-3 capitalize">{{ t.tipo }}</td>
                            <td class="p-3">{{ t.creador }}</td>
                            <td class="p-3 text-xs">{{ t.fecha }}</td>
                            <td class="p-3 text-right" :class="t.items_diferentes > 0 ? 'text-amber-600 font-bold' : ''">{{ t.items_diferentes }}</td>
                            <td class="p-3 text-center">
                                <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(t.estado)]">{{ t.estado }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modal" @click.self="modal = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Nuevo conteo físico</h3>
                <div class="space-y-3">
                    <div><label class="text-xs font-semibold">Ubicación (ID)</label>
                        <input type="number" v-model.number="form.ubicacion_id" class="input w-full" placeholder="ID de la ubicación" autofocus/>
                        <p class="text-xs text-surface-500 mt-1">Ver IDs en <a href="/app/dropi/ubicaciones" class="text-brand-600 hover:underline">Dropi · Ubicaciones</a></p>
                    </div>
                    <div><label class="text-xs font-semibold">Tipo</label>
                        <select v-model="form.tipo" class="input w-full">
                            <option value="ciclico">Cíclico</option>
                            <option value="total">Total</option>
                            <option value="puntual">Puntual</option>
                        </select>
                    </div>
                    <div><label class="text-xs font-semibold">Alcance</label><input v-model="form.alcance" class="input w-full" placeholder="Descripción del alcance"/></div>
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
