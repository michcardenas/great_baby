<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Receipt, Plus, CheckCircle, XCircle, CreditCard } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ gastos: Object, conteos: Object, esAdmin: Boolean, filtros: Object });

const modal = ref(false);
const form = reactive({
    fecha: new Date().toISOString().slice(0, 10),
    categoria: 'papeleria', descripcion: '', monto: 0, proveedor: '', factura_ref: '',
    metodo_pago: 'transferencia', tipo: 'gasto', notas: '',
});
const modalRech = ref(null);
const motivoRech = ref('');
const procesando = ref(false);

const crear = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post('/app/gastos', form, {
        preserveScroll: true,
        onSuccess: () => { modal.value = false; Object.assign(form, { descripcion: '', monto: 0, proveedor: '', factura_ref: '', notas: '' }); },
        onFinish: () => procesando.value = false,
    });
};
const aprobar = (id) => { if (confirm('¿Aprobar este gasto?')) router.post(`/app/gastos/${id}/aprobar`, {}, { preserveScroll: true }); };
const rechazar = () => {
    if (motivoRech.value.trim().length < 10) return;
    router.post(`/app/gastos/${modalRech.value.id}/rechazar`, { motivo: motivoRech.value }, {
        preserveScroll: true, onSuccess: () => { modalRech.value = null; motivoRech.value = ''; },
    });
};
const marcarPagado = (id) => { if (confirm('¿Marcar como pagado?')) router.post(`/app/gastos/${id}/pagado`, {}, { preserveScroll: true }); };
const filtrar = (campo, val) => router.get('/app/gastos', { ...props.filtros, [campo]: val || null });

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
const badge = (e) => ({ pendiente: 'bg-amber-100 text-amber-800', aprobado: 'bg-emerald-100 text-emerald-800', rechazado: 'bg-red-100 text-red-800', pagado: 'bg-brand-100 text-brand-800' }[e] || 'bg-surface-100');
</script>

<template>
    <Head title="Gastos y reembolsos"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between">
                <h1 class="text-2xl font-bold flex items-center gap-2"><Receipt class="h-6 w-6 text-brand-600"/>Gastos operativos y reembolsos</h1>
                <button @click="modal = true" class="btn-primary"><Plus class="h-4 w-4"/> Nueva solicitud</button>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">{{ $page.props.flash.success }}</div>

            <div class="grid grid-cols-4 gap-3">
                <div v-for="e in ['pendiente','aprobado','pagado','rechazado']" :key="e" class="card p-3">
                    <div class="text-xs uppercase capitalize text-surface-500">{{ e }}</div>
                    <div class="text-2xl font-bold mt-1">{{ conteos[e]?.total || 0 }}</div>
                    <div class="text-xs text-surface-500">{{ money(conteos[e]?.suma || 0) }}</div>
                </div>
            </div>

            <div class="card p-3 flex items-center gap-2 flex-wrap">
                <button v-for="t in ['','gasto','reembolso']" :key="t" @click="filtrar('tipo', t)"
                    :class="['px-3 py-1 rounded text-sm capitalize', (filtros.tipo || '') === t ? 'bg-brand-600 text-white' : 'bg-surface-100']">
                    {{ t || 'Todos' }}
                </button>
                <span class="mx-2 text-surface-300">|</span>
                <button v-for="e in ['','pendiente','aprobado','pagado','rechazado']" :key="e" @click="filtrar('estado', e)"
                    :class="['px-3 py-1 rounded text-sm capitalize', (filtros.estado || '') === e ? 'bg-brand-600 text-white' : 'bg-surface-100']">
                    {{ e || 'Todos' }}
                </button>
            </div>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-2">Número</th>
                            <th class="text-left p-2">Fecha</th>
                            <th class="text-left p-2">Tipo</th>
                            <th class="text-left p-2">Categoría</th>
                            <th class="text-left p-2">Descripción</th>
                            <th class="text-right p-2">Monto</th>
                            <th class="text-left p-2">Solicita</th>
                            <th class="text-center p-2">Estado</th>
                            <th class="text-right p-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="g in gastos.data" :key="g.id" class="hover:bg-surface-50">
                            <td class="p-2 font-mono">{{ g.numero }}</td>
                            <td class="p-2 text-xs">{{ g.fecha }}</td>
                            <td class="p-2 text-xs capitalize">{{ g.tipo }}</td>
                            <td class="p-2 text-xs">{{ g.categoria }}</td>
                            <td class="p-2">{{ g.descripcion }}</td>
                            <td class="p-2 text-right font-bold">{{ money(g.monto) }}</td>
                            <td class="p-2 text-xs">{{ g.solicita }}</td>
                            <td class="p-2 text-center"><span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badge(g.estado)]">{{ g.estado }}</span></td>
                            <td class="p-2 text-right space-x-1">
                                <button v-if="esAdmin && g.estado === 'pendiente'" @click="aprobar(g.id)" class="btn-ghost p-1 text-emerald-600" title="Aprobar"><CheckCircle class="h-4 w-4"/></button>
                                <button v-if="esAdmin && g.estado === 'pendiente'" @click="modalRech = g" class="btn-ghost p-1 text-red-600" title="Rechazar"><XCircle class="h-4 w-4"/></button>
                                <button v-if="esAdmin && g.estado === 'aprobado'" @click="marcarPagado(g.id)" class="btn-ghost p-1 text-brand-600" title="Marcar pagado"><CreditCard class="h-4 w-4"/></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modal" @click.self="modal = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold mb-3">Nueva solicitud</h3>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="text-xs font-semibold">Fecha</label><input type="date" v-model="form.fecha" class="input w-full"/></div>
                        <div><label class="text-xs font-semibold">Tipo</label>
                            <select v-model="form.tipo" class="input w-full">
                                <option value="gasto">Gasto operativo</option><option value="reembolso">Reembolso</option>
                            </select>
                        </div>
                        <div><label class="text-xs font-semibold">Categoría</label>
                            <select v-model="form.categoria" class="input w-full">
                                <option value="servicios_publicos">Servicios públicos</option>
                                <option value="arriendo">Arriendo</option>
                                <option value="transporte">Transporte</option>
                                <option value="viaticos">Viáticos</option>
                                <option value="papeleria">Papelería</option>
                                <option value="publicidad">Publicidad</option>
                                <option value="mantenimiento">Mantenimiento</option>
                                <option value="otros">Otros</option>
                            </select>
                        </div>
                        <div><label class="text-xs font-semibold">Método pago</label>
                            <select v-model="form.metodo_pago" class="input w-full">
                                <option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option>
                                <option value="tarjeta">Tarjeta</option><option value="nequi">Nequi</option><option value="daviplata">Daviplata</option>
                            </select>
                        </div>
                        <div><label class="text-xs font-semibold">Monto</label><input type="number" step="0.01" v-model.number="form.monto" class="input w-full" autofocus/></div>
                        <div><label class="text-xs font-semibold">Proveedor</label><input v-model="form.proveedor" class="input w-full"/></div>
                    </div>
                    <div><label class="text-xs font-semibold">Descripción</label><input v-model="form.descripcion" class="input w-full"/></div>
                    <div><label class="text-xs font-semibold">Referencia factura</label><input v-model="form.factura_ref" class="input w-full"/></div>
                    <div><label class="text-xs font-semibold">Notas</label><textarea v-model="form.notas" rows="2" class="input w-full"></textarea></div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modal = false" class="btn-ghost">Cancelar</button>
                    <button @click="crear" :disabled="procesando" class="btn-primary">{{ procesando ? '…' : 'Solicitar' }}</button>
                </div>
            </div>
        </div>

        <div v-if="modalRech" @click.self="modalRech = null" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Rechazar {{ modalRech.numero }}</h3>
                <textarea v-model="motivoRech" rows="3" class="input w-full" placeholder="Motivo mínimo 10 caracteres" autofocus/>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modalRech = null" class="btn-ghost">Cancelar</button>
                    <button @click="rechazar" :disabled="motivoRech.trim().length < 10" class="btn-primary bg-red-600">Rechazar</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
