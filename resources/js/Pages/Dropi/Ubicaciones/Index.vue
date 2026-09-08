<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { MapPin, Plus, Edit2, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    ubicaciones: { type: Object, required: true },
});

const modal = ref(false);
const editando = ref(null);
const form = reactive({
    id: null, codigo: '', nombre: '', categoria: 'general',
    disponible_para_venta: true, activa: true, notas: '',
});
const abrir = (u = null) => {
    editando.value = u;
    if (u) Object.assign(form, u);
    else Object.assign(form, { id: null, codigo: '', nombre: '', categoria: 'general', disponible_para_venta: true, activa: true, notas: '' });
    modal.value = true;
};
const procesando = ref(false);
const guardar = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post('/app/dropi/ubicaciones', form, {
        preserveScroll: true,
        onSuccess: () => { modal.value = false; },
        onFinish: () => procesando.value = false,
    });
};
const eliminar = (u) => {
    if (!confirm(`Eliminar ubicación ${u.codigo}?`)) return;
    router.delete(`/app/dropi/ubicaciones/${u.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Ubicaciones inventario"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <MapPin class="h-6 w-6 text-brand-600"/>
                    Ubicaciones de inventario
                </h1>
                <button @click="abrir()" class="btn-primary"><Plus class="h-4 w-4"/> Nueva</button>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">Código</th>
                            <th class="text-left p-3">Nombre</th>
                            <th class="text-left p-3">Categoría</th>
                            <th class="text-center p-3">Venta</th>
                            <th class="text-center p-3">Activa</th>
                            <th class="text-right p-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="u in ubicaciones.data" :key="u.id" class="hover:bg-surface-50">
                            <td class="p-3 font-mono font-bold">{{ u.codigo }}</td>
                            <td class="p-3">{{ u.nombre }}</td>
                            <td class="p-3 text-xs">{{ u.categoria }}</td>
                            <td class="p-3 text-center">{{ u.disponible_para_venta ? '✓' : '—' }}</td>
                            <td class="p-3 text-center">{{ u.activa ? '✓' : '—' }}</td>
                            <td class="p-3 text-right space-x-1">
                                <button @click="abrir(u)" class="btn-ghost p-1"><Edit2 class="h-4 w-4"/></button>
                                <button @click="eliminar(u)" class="btn-ghost p-1 text-red-600"><Trash2 class="h-4 w-4"/></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modal" @click.self="modal = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">{{ editando ? 'Editar' : 'Nueva' }} ubicación</h3>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="text-xs font-semibold">Código</label><input v-model="form.codigo" class="input w-full" autofocus/></div>
                        <div><label class="text-xs font-semibold">Categoría</label>
                            <select v-model="form.categoria" class="input w-full">
                                <option value="general">General</option>
                                <option value="produccion">Producción</option>
                                <option value="reservado">Reservado</option>
                                <option value="cuarentena">Cuarentena</option>
                                <option value="danado">Dañado</option>
                            </select>
                        </div>
                    </div>
                    <div><label class="text-xs font-semibold">Nombre</label><input v-model="form.nombre" class="input w-full"/></div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.disponible_para_venta"/> Disponible para venta</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="form.activa"/> Activa</label>
                    </div>
                    <div><label class="text-xs font-semibold">Notas</label><textarea v-model="form.notas" rows="2" class="input w-full"></textarea></div>
                </div>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <button @click="modal = false" class="btn-ghost">Cancelar</button>
                    <button @click="guardar" :disabled="procesando" class="btn-primary">{{ procesando ? '…' : 'Guardar' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
