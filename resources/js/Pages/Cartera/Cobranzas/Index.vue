<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { MessageSquare, Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ cobros: { type: Object, required: true } });
const modal = ref(false);
const form = reactive({ factura_id: '', canal: 'whatsapp', tramo: '30d', mensaje: '' });
const procesando = ref(false);
const crear = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post('/app/cartera/cobranzas', form, {
        preserveScroll: true,
        onSuccess: () => { modal.value = false; Object.assign(form, { factura_id: '', mensaje: '' }); },
        onFinish: () => procesando.value = false,
    });
};
const badgeCanal = (c) => ({ whatsapp: 'bg-emerald-100 text-emerald-800', email: 'bg-blue-100 text-blue-800', llamada: 'bg-amber-100 text-amber-800', visita: 'bg-purple-100 text-purple-800', sms: 'bg-brand-100 text-brand-800' }[c] || 'bg-surface-100');
</script>

<template>
    <Head title="Registros de cobranza"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between">
                <h1 class="text-2xl font-bold flex items-center gap-2"><MessageSquare class="h-6 w-6 text-brand-600"/>Registros de cobranza</h1>
                <button @click="modal = true" class="btn-primary"><Plus class="h-4 w-4"/> Nuevo</button>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">{{ $page.props.flash.success }}</div>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">Factura</th>
                            <th class="text-center p-3">Canal</th>
                            <th class="text-left p-3">Tramo</th>
                            <th class="text-left p-3">Estado</th>
                            <th class="text-left p-3">Mensaje</th>
                            <th class="text-left p-3">Gestor</th>
                            <th class="text-left p-3">Enviado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="c in cobros.data" :key="c.id" class="hover:bg-surface-50">
                            <td class="p-3 font-mono text-xs">{{ c.factura }}</td>
                            <td class="p-3 text-center"><span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badgeCanal(c.canal)]">{{ c.canal }}</span></td>
                            <td class="p-3 text-xs">{{ c.tramo }}</td>
                            <td class="p-3 text-xs">{{ c.estado }}</td>
                            <td class="p-3 text-xs max-w-md truncate" :title="c.mensaje">{{ c.mensaje }}</td>
                            <td class="p-3 text-xs">{{ c.gestor }}</td>
                            <td class="p-3 text-xs">{{ c.enviado_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modal" @click.self="modal = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Registrar cobranza</h3>
                <div class="space-y-3">
                    <div><label class="text-xs font-semibold">ID Factura</label><input type="number" v-model.number="form.factura_id" class="input w-full" autofocus/></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="text-xs font-semibold">Canal</label>
                            <select v-model="form.canal" class="input w-full">
                                <option value="whatsapp">WhatsApp</option><option value="email">Email</option>
                                <option value="llamada">Llamada</option><option value="visita">Visita</option><option value="sms">SMS</option>
                            </select>
                        </div>
                        <div><label class="text-xs font-semibold">Tramo</label>
                            <select v-model="form.tramo" class="input w-full">
                                <option value="0d">Recordatorio</option><option value="30d">30 días</option>
                                <option value="60d">60 días</option><option value="90d">90 días</option><option value="120+">120+ días</option>
                            </select>
                        </div>
                    </div>
                    <div><label class="text-xs font-semibold">Mensaje</label><textarea v-model="form.mensaje" rows="4" class="input w-full"></textarea></div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modal = false" class="btn-ghost">Cancelar</button>
                    <button @click="crear" :disabled="procesando" class="btn-primary">{{ procesando ? '…' : 'Registrar' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
