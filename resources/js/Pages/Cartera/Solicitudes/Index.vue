<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { FileCheck, Check, X } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ solicitudes: { type: Object, required: true }, filtro: { type: String, default: '' } });

const modal = ref(null);
const form = reactive({ decision: 'aprobar', notas: '' });
const procesando = ref(false);
const abrir = (s) => { modal.value = s; form.decision = 'aprobar'; form.notas = ''; };
const resolver = () => {
    if (form.notas.trim().length < 10 || procesando.value) return;
    procesando.value = true;
    router.post(`/app/cartera/solicitudes/${modal.value.id}/resolver`, form, {
        preserveScroll: true,
        onSuccess: () => { modal.value = null; },
        onFinish: () => procesando.value = false,
    });
};
const filtrar = (e) => router.get('/app/cartera/solicitudes', { estado: e || null });
const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
const badge = (e) => ({ pendiente: 'bg-amber-100 text-amber-800', aprobada: 'bg-emerald-100 text-emerald-800', rechazada: 'bg-red-100 text-red-800', escalada: 'bg-blue-100 text-blue-800' }[e] || 'bg-surface-100');
</script>

<template>
    <Head title="Solicitudes de crédito"/>
    <AppLayout>
        <div class="space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2"><FileCheck class="h-6 w-6 text-brand-600"/>Solicitudes de crédito (excepciones)</h1>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">{{ $page.props.flash.success }}</div>

            <div class="card p-3 flex items-center gap-2">
                <button v-for="f in ['','pendiente','aprobada','rechazada','escalada']" :key="f" @click="filtrar(f)"
                    :class="['px-3 py-1 rounded text-sm capitalize', filtro === f ? 'bg-brand-600 text-white' : 'bg-surface-100 hover:bg-surface-200']">
                    {{ f || 'Todas' }}
                </button>
            </div>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">Cliente</th>
                            <th class="text-right p-3">Monto</th>
                            <th class="text-left p-3">Motivo retención</th>
                            <th class="text-center p-3">Estado</th>
                            <th class="text-left p-3">Solicitó</th>
                            <th class="text-left p-3">Creada</th>
                            <th class="text-right p-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="s in solicitudes.data" :key="s.id" class="hover:bg-surface-50">
                            <td class="p-3 font-medium">{{ s.contacto }}</td>
                            <td class="p-3 text-right font-bold">{{ money(s.monto_pedido) }}</td>
                            <td class="p-3 text-xs max-w-xs truncate" :title="s.motivo">{{ s.motivo }}</td>
                            <td class="p-3 text-center"><span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(s.estado)]">{{ s.estado }}</span></td>
                            <td class="p-3 text-xs">{{ s.solicitada_por }}</td>
                            <td class="p-3 text-xs">{{ s.creada }}</td>
                            <td class="p-3 text-right">
                                <button v-if="s.estado === 'pendiente' || s.estado === 'escalada'" @click="abrir(s)" class="btn-primary text-xs">Resolver</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modal" @click.self="modal = null" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Resolver solicitud</h3>
                <p class="text-sm text-surface-500 mb-3">{{ modal.contacto }} · {{ money(modal.monto_pedido) }}</p>
                <div class="grid grid-cols-3 gap-2 mb-3">
                    <label v-for="opt in [{v:'aprobar',c:'emerald',t:'Aprobar'},{v:'rechazar',c:'red',t:'Rechazar'},{v:'escalar',c:'blue',t:'Escalar'}]" :key="opt.v"
                        :class="['border rounded p-2 text-center cursor-pointer text-sm', form.decision === opt.v ? `border-${opt.c}-600 bg-${opt.c}-50 font-bold` : '']">
                        <input type="radio" v-model="form.decision" :value="opt.v" class="hidden"/>{{ opt.t }}
                    </label>
                </div>
                <label class="text-xs font-semibold">Notas (mín 10 chars)</label>
                <textarea v-model="form.notas" rows="3" class="input w-full" autofocus/>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modal = null" class="btn-ghost">Cancelar</button>
                    <button @click="resolver" :disabled="procesando || form.notas.trim().length < 10" class="btn-primary">Confirmar</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
