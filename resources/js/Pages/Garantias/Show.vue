<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ShieldCheck, CheckCircle, XCircle, Truck, Lock } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ ticket: { type: Object, required: true } });

const modalDecidir = ref(false);
const decision = ref('aprobar');
const notas = ref('');
const procesando = ref(false);
const modalCerrar = ref(false);
const notasCierre = ref('');

const decidir = () => {
    if (notas.value.trim().length < 10 || procesando.value) return;
    procesando.value = true;
    router.post(`/app/garantias/${props.ticket.id}/decidir`, { decision: decision.value, notas_decision: notas.value }, {
        onSuccess: () => { modalDecidir.value = false; notas.value = ''; },
        onFinish: () => procesando.value = false,
    });
};
const iniciarReposicion = () => {
    if (!confirm('Iniciar reposición al cliente por valor $0 (según contrato)?')) return;
    router.post(`/app/garantias/${props.ticket.id}/reposicion`);
};
const cerrar = () => {
    router.post(`/app/garantias/${props.ticket.id}/cerrar`, { notas: notasCierre.value }, {
        onSuccess: () => { modalCerrar.value = false; },
    });
};

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
const badge = (e) => ({
    abierto: 'bg-blue-100 text-blue-800', en_revision: 'bg-amber-100 text-amber-800',
    aprobada: 'bg-emerald-100 text-emerald-800', en_reposicion: 'bg-brand-100 text-brand-800',
    rechazada: 'bg-red-100 text-red-800', cerrada: 'bg-surface-200 text-surface-600',
}[e] || 'bg-surface-100');
</script>

<template>
    <Head :title="`Garantía ${ticket.numero}`"/>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-4">
            <Link href="/app/garantias" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">{{ $page.props.flash.success }}</div>

            <div class="card p-5">
                <div class="flex items-start justify-between flex-wrap gap-3">
                    <div>
                        <div class="text-xs text-surface-500 uppercase">Garantía</div>
                        <h1 class="text-2xl font-bold font-mono">{{ ticket.numero }}</h1>
                        <div class="text-xs text-surface-500 mt-1">Creada {{ ticket.creado }} por {{ ticket.creador }}</div>
                    </div>
                    <div class="text-right">
                        <span :class="['inline-block px-3 py-1 rounded font-bold text-sm uppercase', badge(ticket.estado)]">{{ ticket.estado }}</span>
                        <div v-if="ticket.plazo_concepto_at" class="text-xs text-surface-500 mt-1">Plazo concepto: {{ ticket.plazo_concepto_at }}</div>
                    </div>
                </div>

                <div class="flex items-center gap-2 border-t pt-3 mt-3">
                    <button v-if="['abierto','en_revision'].includes(ticket.estado)" @click="modalDecidir = true" class="btn-primary">
                        <CheckCircle class="h-4 w-4"/> Decidir
                    </button>
                    <button v-if="ticket.estado === 'aprobada'" @click="iniciarReposicion" class="btn-primary bg-emerald-600 hover:bg-emerald-700">
                        <Truck class="h-4 w-4"/> Iniciar reposición ($0)
                    </button>
                    <button v-if="['en_reposicion','rechazada'].includes(ticket.estado)" @click="modalCerrar = true" class="btn-ghost">
                        <Lock class="h-4 w-4"/> Cerrar ticket
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-2">Cliente</div>
                    <div class="font-semibold">{{ ticket.cliente_nombre }}</div>
                    <div class="text-sm text-surface-500">{{ ticket.cliente_telefono || '—' }}</div>
                    <a v-if="ticket.cliente_telefono" :href="`https://wa.me/57${ticket.cliente_telefono.replace(/\D/g,'')}?text=${encodeURIComponent('Hola, sobre tu garantía ' + ticket.numero)}`"
                        target="_blank" class="text-xs text-emerald-600 hover:underline block mt-2">📱 Contactar por WhatsApp</a>
                </div>
                <div v-if="ticket.pedido_original" class="card p-4">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-2">Pedido original</div>
                    <div class="font-mono">{{ ticket.pedido_original.guia }}</div>
                    <div class="text-sm text-surface-500">{{ ticket.pedido_original.cliente }}</div>
                </div>
            </div>

            <div class="card p-4">
                <div class="text-xs uppercase font-bold text-brand-600 mb-2">Falla reportada · Cantidad: {{ ticket.cantidad }}</div>
                <p class="text-sm whitespace-pre-wrap">{{ ticket.descripcion_falla }}</p>
            </div>

            <div v-if="ticket.fotos.length" class="card p-4">
                <div class="text-xs uppercase font-bold text-brand-600 mb-2">Evidencia fotográfica ({{ ticket.fotos.length }})</div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <a v-for="(f, i) in ticket.fotos" :key="i" :href="f" target="_blank">
                        <img :src="f" class="w-full h-40 object-cover rounded border hover:opacity-80"/>
                    </a>
                </div>
            </div>

            <div v-if="ticket.notas_decision" class="card p-4">
                <div class="text-xs uppercase font-bold text-brand-600 mb-2">Decisión ({{ ticket.decidio_por }} · {{ ticket.decision_at }})</div>
                <p class="text-sm whitespace-pre-wrap">{{ ticket.notas_decision }}</p>
            </div>
        </div>

        <div v-if="modalDecidir" @click.self="modalDecidir = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Decidir garantía</h3>
                <div class="grid grid-cols-2 gap-2 mb-3">
                    <label :class="['border rounded-lg p-3 text-center cursor-pointer', decision === 'aprobar' ? 'border-emerald-600 bg-emerald-50' : '']">
                        <input type="radio" v-model="decision" value="aprobar" class="hidden"/>
                        <CheckCircle class="h-6 w-6 mx-auto text-emerald-600"/>
                        <div class="text-sm font-semibold mt-1">Aprobar</div>
                    </label>
                    <label :class="['border rounded-lg p-3 text-center cursor-pointer', decision === 'rechazar' ? 'border-red-600 bg-red-50' : '']">
                        <input type="radio" v-model="decision" value="rechazar" class="hidden"/>
                        <XCircle class="h-6 w-6 mx-auto text-red-600"/>
                        <div class="text-sm font-semibold mt-1">Rechazar</div>
                    </label>
                </div>
                <label class="text-xs font-semibold">Notas (mín 10 caracteres)</label>
                <textarea v-model="notas" rows="3" class="input w-full" autofocus placeholder="Motivo detallado..."/>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modalDecidir = false" class="btn-ghost">Cancelar</button>
                    <button @click="decidir" :disabled="procesando || notas.trim().length < 10" class="btn-primary">Confirmar</button>
                </div>
            </div>
        </div>

        <div v-if="modalCerrar" @click.self="modalCerrar = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Cerrar ticket</h3>
                <textarea v-model="notasCierre" rows="3" class="input w-full" placeholder="Notas de cierre (opcional)" autofocus/>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modalCerrar = false" class="btn-ghost">Cancelar</button>
                    <button @click="cerrar" class="btn-primary">Cerrar</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
