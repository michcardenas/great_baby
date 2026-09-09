<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, Plus, ExternalLink } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useEscClose } from '@/composables/useEscClose';
import { useFecha } from '@/composables/useFecha';

const props = defineProps({
    tomas: { type: Object, required: true },
    ubicaciones: { type: Array, default: () => [] },
});
const { fechaCorta } = useFecha();

const modal = ref(false);
useEscClose(modal);

const form = reactive({ ubicacion_id: '', tipo: 'ciclico', alcance: '', observaciones: '' });
const errores = ref({});
const procesando = ref(false);

const crear = () => {
    if (procesando.value) return;
    procesando.value = true;
    errores.value = {};
    router.post('/app/inventario/conteos', form, {
        preserveScroll: true,
        onSuccess: () => {
            modal.value = false;
            Object.assign(form, { ubicacion_id: '', tipo: 'ciclico', alcance: '', observaciones: '' });
        },
        onError: (e) => { errores.value = e; },
        onFinish: () => (procesando.value = false),
    });
};

const badge = (e) => ({
    borrador: 'bg-surface-200 text-surface-800',
    en_conteo: 'bg-amber-100 text-amber-800',
    ajustada: 'bg-emerald-100 text-emerald-800',
    anulada: 'bg-red-100 text-red-800',
}[e] || 'bg-surface-100');
</script>

<template>
    <Head title="Conteos físicos"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between flex-wrap gap-3">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <ClipboardList class="h-6 w-6 text-brand-600"/>Conteos físicos
                </h1>
                <button @click="modal = true" class="btn-primary min-h-11"><Plus class="h-4 w-4"/> Nuevo conteo</button>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="p-3 rounded-lg bg-red-500/15 border-l-4 border-red-500 text-red-700 text-sm">
                {{ $page.props.flash.error }}
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
                            <th class="text-center p-3">Abrir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!tomas.data.length"><td colspan="8" class="p-6 text-center text-surface-500">Sin conteos aún.</td></tr>
                        <tr v-for="t in tomas.data" :key="t.id" class="hover:bg-surface-50">
                            <td class="p-3 font-mono font-bold">{{ t.numero }}</td>
                            <td class="p-3">{{ t.ubicacion }}</td>
                            <td class="p-3 capitalize">{{ t.tipo }}</td>
                            <td class="p-3">{{ t.creador }}</td>
                            <td class="p-3 text-xs">{{ fechaCorta(t.fecha) }}</td>
                            <td class="p-3 text-right" :class="t.items_diferentes > 0 ? 'text-amber-600 font-bold' : ''">{{ t.items_diferentes }}</td>
                            <td class="p-3 text-center">
                                <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(t.estado)]">{{ t.estado }}</span>
                            </td>
                            <td class="p-3 text-center">
                                <Link :href="`/app/inventario/conteos/${t.id}`" class="text-brand-600 hover:underline inline-flex items-center gap-1 text-xs">
                                    <ExternalLink class="h-3 w-3"/> Abrir
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="tomas.links && tomas.links.length > 3" class="flex justify-center gap-1 flex-wrap">
                <Link v-for="l in tomas.links" :key="l.label" :href="l.url || '#'" preserve-scroll
                    v-html="l.label"
                    :class="['px-3 py-1 text-xs rounded', l.active ? 'bg-brand-600 text-white' : 'bg-surface-100 hover:bg-surface-200', !l.url && 'opacity-40 pointer-events-none']"/>
            </div>
        </div>

        <div v-if="modal" @click.self="modal = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Nuevo conteo físico</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-semibold">Ubicación</label>
                        <select v-model.number="form.ubicacion_id" class="input w-full" autofocus>
                            <option value="">— Selecciona —</option>
                            <option v-for="u in ubicaciones" :key="u.id" :value="u.id">{{ u.codigo }} · {{ u.nombre }}</option>
                        </select>
                        <p v-if="errores.ubicacion_id" class="text-xs text-red-600 mt-1">{{ errores.ubicacion_id }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Tipo</label>
                        <select v-model="form.tipo" class="input w-full">
                            <option value="ciclico">Cíclico (sólo con stock)</option>
                            <option value="total">Total (todo el catálogo)</option>
                            <option value="puntual">Puntual (por alcance)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Alcance (opcional)</label>
                        <input v-model="form.alcance" class="input w-full" placeholder="Ej: marca:5 o categoria:12" />
                        <p class="text-xs text-surface-500 mt-1">Formato: <code>marca:ID</code> o <code>categoria:ID</code></p>
                        <p v-if="errores.alcance" class="text-xs text-red-600 mt-1">{{ errores.alcance }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Observaciones</label>
                        <textarea v-model="form.observaciones" rows="2" class="input w-full"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modal = false" class="btn-ghost min-h-11">Cancelar</button>
                    <button @click="crear" :disabled="procesando" class="btn-primary min-h-11">{{ procesando ? 'Creando…' : 'Crear' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
