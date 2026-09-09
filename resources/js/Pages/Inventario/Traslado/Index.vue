<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftRight, Plus, ExternalLink } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useEscClose } from '@/composables/useEscClose';
import { useFecha } from '@/composables/useFecha';

const props = defineProps({
    traslados: { type: Object, required: true },
    ubicaciones: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
});

const { fechaCorta } = useFecha();

const modal = ref(false);
useEscClose(modal);

const form = reactive({ origen_id: '', destino_id: '', motivo: '', observaciones: '' });
const errores = ref({});
const procesando = ref(false);

const crear = () => {
    if (procesando.value) return;
    procesando.value = true;
    errores.value = {};
    router.post('/app/inventario/traslados', form, {
        preserveScroll: true,
        onSuccess: () => {
            modal.value = false;
            Object.assign(form, { origen_id: '', destino_id: '', motivo: '', observaciones: '' });
        },
        onError: (e) => { errores.value = e; },
        onFinish: () => (procesando.value = false),
    });
};

const badge = (e) => ({
    borrador: 'bg-surface-200 text-surface-800',
    en_transito: 'bg-amber-100 text-amber-800',
    recibido: 'bg-emerald-100 text-emerald-800',
    anulado: 'bg-red-100 text-red-800',
}[e] || 'bg-surface-100');

const filtroEstado = ref(props.filtros.estado || '');
const aplicarFiltro = () => router.get('/app/inventario/traslados', { estado: filtroEstado.value || undefined }, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head title="Traslados de inventario" />
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between flex-wrap gap-3">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <ArrowLeftRight class="h-6 w-6 text-brand-600" />
                    Traslados entre ubicaciones
                </h1>
                <div class="flex gap-2">
                    <select v-model="filtroEstado" @change="aplicarFiltro" class="input">
                        <option value="">Todos los estados</option>
                        <option value="borrador">Borrador</option>
                        <option value="en_transito">En tránsito</option>
                        <option value="recibido">Recibido</option>
                        <option value="anulado">Anulado</option>
                    </select>
                    <button @click="modal = true" class="btn-primary min-h-11">
                        <Plus class="h-4 w-4" /> Nuevo traslado
                    </button>
                </div>
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
                            <th class="text-left p-3">Origen → Destino</th>
                            <th class="text-left p-3">Motivo</th>
                            <th class="text-left p-3">Solicitante</th>
                            <th class="text-left p-3">Fecha</th>
                            <th class="text-center p-3">Estado</th>
                            <th class="text-center p-3">Abrir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="!traslados.data.length"><td colspan="7" class="p-6 text-center text-surface-500">No hay traslados. Crea uno con el botón de arriba.</td></tr>
                        <tr v-for="t in traslados.data" :key="t.id" class="hover:bg-surface-50">
                            <td class="p-3 font-mono font-bold">{{ t.numero }}</td>
                            <td class="p-3 text-xs">{{ t.origen }} → {{ t.destino }}</td>
                            <td class="p-3">{{ t.motivo }}</td>
                            <td class="p-3">{{ t.solicitante }}</td>
                            <td class="p-3 text-xs">{{ fechaCorta(t.fecha) }}</td>
                            <td class="p-3 text-center">
                                <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(t.estado)]">{{ t.estado }}</span>
                            </td>
                            <td class="p-3 text-center">
                                <Link :href="`/app/inventario/traslados/${t.id}`" class="text-brand-600 hover:underline inline-flex items-center gap-1 text-xs">
                                    <ExternalLink class="h-3 w-3" /> Abrir
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="traslados.links && traslados.links.length > 3" class="flex justify-center gap-1 flex-wrap">
                <Link v-for="l in traslados.links" :key="l.label" :href="l.url || '#'" preserve-scroll
                    v-html="l.label"
                    :class="['px-3 py-1 text-xs rounded', l.active ? 'bg-brand-600 text-white' : 'bg-surface-100 hover:bg-surface-200', !l.url && 'opacity-40 pointer-events-none']"/>
            </div>
        </div>

        <div v-if="modal" @click.self="modal = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Nuevo traslado</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-semibold">Origen</label>
                        <select v-model.number="form.origen_id" class="input w-full" autofocus>
                            <option value="">— Selecciona una ubicación —</option>
                            <option v-for="u in ubicaciones" :key="u.id" :value="u.id">{{ u.codigo }} · {{ u.nombre }}</option>
                        </select>
                        <p v-if="errores.origen_id" class="text-xs text-red-600 mt-1">{{ errores.origen_id }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Destino</label>
                        <select v-model.number="form.destino_id" class="input w-full">
                            <option value="">— Selecciona una ubicación —</option>
                            <option v-for="u in ubicaciones" :key="u.id" :value="u.id" :disabled="u.id === form.origen_id">{{ u.codigo }} · {{ u.nombre }}</option>
                        </select>
                        <p v-if="errores.destino_id" class="text-xs text-red-600 mt-1">{{ errores.destino_id }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Motivo</label>
                        <select v-model="form.motivo" class="input w-full">
                            <option value="">— Selecciona —</option>
                            <option value="reposicion">Reposición stock venta</option>
                            <option value="averia">Envío por avería</option>
                            <option value="correccion">Corrección de inventario</option>
                            <option value="prestamo">Préstamo entre bodegas</option>
                            <option value="otro">Otro</option>
                        </select>
                        <p v-if="errores.motivo" class="text-xs text-red-600 mt-1">{{ errores.motivo }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Observaciones</label>
                        <textarea v-model="form.observaciones" rows="2" class="input w-full" placeholder="Opcional"></textarea>
                    </div>
                    <p class="text-xs text-surface-500">Después de crear, agrega los productos en la pantalla del traslado.</p>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modal = false" class="btn-ghost min-h-11">Cancelar</button>
                    <button @click="crear" :disabled="procesando" class="btn-primary min-h-11">{{ procesando ? 'Creando…' : 'Crear' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
