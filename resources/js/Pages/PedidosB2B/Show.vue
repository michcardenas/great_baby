<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { ArrowLeft, CheckCircle, XCircle, FileText, User } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    pedido: { type: Object, required: true },
    items: { type: Array, required: true },
});

const money = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0);
const modalRechazar = ref(false);
const modalConfirmar = ref(null); // { tipo: 'aprobar'|'facturar', msg }
const motivo = ref('');
const motivoError = ref('');
const procesando = ref(false);

const notifyError = (mensaje) => {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('gb:error', { detail: { mensaje } }));
    }
};

const pedirAprobar = () => modalConfirmar.value = { tipo: 'aprobar', msg: '¿Aprobar este pedido? Podrás facturarlo después.' };
const pedirFacturar = () => modalConfirmar.value = { tipo: 'facturar', msg: `Facturar por ${money(props.pedido.total)}?` };

const ejecutarConfirmacion = () => {
    if (!modalConfirmar.value || procesando.value) return;
    const url = modalConfirmar.value.tipo === 'aprobar'
        ? `/app/pedidos-b2b/${props.pedido.id}/aprobar`
        : `/app/pedidos-b2b/${props.pedido.id}/facturar`;
    procesando.value = true;
    router.post(url, {}, {
        preserveScroll: true,
        onSuccess: () => { modalConfirmar.value = null; },
        onError: (e) => {
            const msg = Object.values(e).flat().find(v => typeof v === 'string') || 'No se pudo completar la acción.';
            notifyError(msg);
            modalConfirmar.value = null;
        },
        onFinish: () => { procesando.value = false; },
    });
};

// ESC cierra cualquier modal abierto
useEventListener(typeof window !== 'undefined' ? window : null, 'keydown', (e) => {
    if (e.key === 'Escape') {
        modalConfirmar.value = null;
        modalRechazar.value = false;
    }
});

const rechazar = () => {
    motivoError.value = '';
    if (motivo.value.trim().length < 10) {
        motivoError.value = 'El motivo debe tener al menos 10 caracteres.';
        return;
    }
    procesando.value = true;
    router.post(`/app/pedidos-b2b/${props.pedido.id}/rechazar`, { motivo: motivo.value }, {
        preserveScroll: true,
        onSuccess: () => { modalRechazar.value = false; motivo.value = ''; },
        onError: (e) => { motivoError.value = e.motivo || 'No se pudo rechazar. Reintenta.'; },
        onFinish: () => { procesando.value = false; },
    });
};
</script>

<template>
    <Head :title="'Pedido ' + pedido.numero"/>
    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-4">
            <Link href="/app/pedidos-b2b" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>

            <div class="card p-6">
                <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                    <div>
                        <div class="text-xs text-surface-500 uppercase">Pedido B2B</div>
                        <h1 class="text-2xl font-bold">{{ pedido.numero }}</h1>
                        <div class="text-xs text-surface-500 mt-1">Recibido {{ pedido.creado }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-surface-500 uppercase">Total</div>
                        <div class="text-3xl font-bold text-brand-600">{{ money(pedido.total) }}</div>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="flex items-center gap-2 flex-wrap border-t border-surface-200 dark:border-surface-800 pt-4">
                    <button v-if="pedido.estado === 'enviado'" @click="pedirAprobar" :disabled="procesando" class="btn-primary">
                        <CheckCircle class="h-4 w-4"/> Aprobar
                    </button>
                    <button v-if="['enviado','aprobado'].includes(pedido.estado)" @click="modalRechazar = true" :disabled="procesando" class="btn-ghost text-red-600 border border-red-300">
                        <XCircle class="h-4 w-4"/> Rechazar
                    </button>
                    <button v-if="pedido.estado === 'aprobado'" @click="pedirFacturar" :disabled="procesando" class="btn-primary bg-emerald-600 hover:bg-emerald-700">
                        <FileText class="h-4 w-4"/> Facturar
                    </button>
                    <Link v-if="pedido.factura" :href="`/app/facturas/${pedido.factura.id}`" class="btn-ghost">
                        <FileText class="h-4 w-4"/> Ver factura {{ pedido.factura.numero }}
                    </Link>
                    <span class="ml-auto text-sm text-surface-500">Estado: <b>{{ pedido.estado }}</b></span>
                </div>

                <div v-if="pedido.motivo_rechazo" class="mt-4 p-3 rounded-lg bg-red-50 border-l-4 border-red-500 text-red-700 text-sm">
                    <b>Motivo de rechazo:</b> {{ pedido.motivo_rechazo }}
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="card p-4 md:col-span-2">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">Ítems ({{ items.length }})</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-surface-500 uppercase">
                                <tr>
                                    <th class="text-left p-2">SKU / Producto</th>
                                    <th class="text-right p-2">Cant.</th>
                                    <th class="text-right p-2">Precio</th>
                                    <th class="text-right p-2">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                                <tr v-for="(it, idx) in items" :key="idx">
                                    <td class="p-2">
                                        <div class="font-mono text-xs text-surface-500">{{ it.sku }}</div>
                                        <div>{{ it.desc }}</div>
                                    </td>
                                    <td class="p-2 text-right">{{ it.cantidad }}</td>
                                    <td class="p-2 text-right">{{ money(it.precio) }}</td>
                                    <td class="p-2 text-right font-bold">{{ money(it.total) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t border-surface-200 dark:border-surface-800">
                                <tr><td colspan="3" class="p-2 text-right text-xs text-surface-500">Subtotal</td><td class="p-2 text-right">{{ money(pedido.subtotal) }}</td></tr>
                                <tr><td colspan="3" class="p-2 text-right text-xs text-surface-500">IVA</td><td class="p-2 text-right">{{ money(pedido.iva) }}</td></tr>
                                <tr class="font-bold"><td colspan="3" class="p-2 text-right">Total</td><td class="p-2 text-right text-brand-600">{{ money(pedido.total) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="card p-4">
                        <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3 flex items-center gap-2">
                            <User class="h-3 w-3"/> Cliente
                        </div>
                        <div class="text-sm space-y-1">
                            <div class="font-semibold">{{ pedido.contacto.nombre }}</div>
                            <div class="text-xs text-surface-500">{{ pedido.contacto.email }}</div>
                            <div class="text-xs text-surface-500">{{ pedido.contacto.telefono }}</div>
                            <div class="text-xs text-surface-500">{{ pedido.contacto.ciudad }}</div>
                            <div class="text-xs text-surface-500 pt-2 border-t border-surface-200 dark:border-surface-800 mt-2">
                                Lista: <b>{{ pedido.lista || '—' }}</b>
                            </div>
                        </div>
                    </div>

                    <div v-if="pedido.notas_cliente" class="card p-4">
                        <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Notas del cliente</div>
                        <p class="text-sm">{{ pedido.notas_cliente }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal rechazar -->
        <div v-if="modalRechazar" @click.self="modalRechazar = false"
            class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-surface-900 rounded-xl shadow-2xl p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Rechazar pedido</h3>
                <label class="block text-xs font-semibold text-surface-600 mb-1">Motivo (mín 10 caracteres, visible para el cliente)</label>
                <textarea v-model="motivo" rows="3" maxlength="500" class="input w-full" autofocus
                    placeholder="Ej: Sin stock disponible en las tallas solicitadas."></textarea>
                <div v-if="motivoError" class="mt-2 text-sm text-red-600">{{ motivoError }}</div>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <button @click="modalRechazar = false" class="btn-ghost">Cancelar</button>
                    <button @click="rechazar" :disabled="procesando" class="btn-primary bg-red-600 hover:bg-red-700">
                        {{ procesando ? 'Rechazando...' : 'Rechazar pedido' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal confirmar aprobar/facturar (reemplaza window.confirm que robaba foco pistola) -->
        <div v-if="modalConfirmar" @click.self="modalConfirmar = null"
            class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-surface-900 rounded-xl shadow-2xl p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Confirmar acción</h3>
                <p class="text-sm text-surface-700 dark:text-surface-300">{{ modalConfirmar.msg }}</p>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <button @click="modalConfirmar = null" class="btn-ghost">Cancelar</button>
                    <button @click="ejecutarConfirmacion" :disabled="procesando" class="btn-primary">
                        {{ procesando ? 'Procesando...' : 'Confirmar' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
