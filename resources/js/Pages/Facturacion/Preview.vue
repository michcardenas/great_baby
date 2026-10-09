<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppConfirmModal from '@/Components/AppConfirmModal.vue';
import { ArrowLeft, FileText, AlertTriangle, CheckCircle2, Send, Mail, Receipt } from 'lucide-vue-next';

const props = defineProps({
    pedido: Object,
    contacto: Object,
    items: Array,
    advertencias: Array,
});

const money = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0);

const sendDian = ref(true);  // default · emite a DIAN
const sendMail = ref(!! props.contacto?.email);  // default solo si hay email
const confirmando = ref(false);

const bloqueado = (props.advertencias ?? []).some(a => a.tipo === 'danger');

/*
 * Confirmación de la emisión REAL a la DIAN.
 *
 * Era un `window.confirm()` del navegador. Dos problemas: es la acción más
 * irreversible del sistema —una factura electrónica radicada no se borra, se
 * anula con nota crédito— y merece algo mejor que un cuadro gris del sistema
 * operativo; y además el confirm nativo queda BLOQUEADO en vistas embebidas,
 * así que el botón no hacía nada y no se veía ningún error.
 */
const modal = ref(null);

const emitir = () => {
    confirmando.value = true;
    modal.value = null;
    router.post(`/app/facturacion/facturar/${props.pedido.id}`, {
        send_dian: sendDian.value,
        send_mail: sendMail.value,
    }, {
        onFinish: () => { confirmando.value = false; },
    });
};

const facturar = () => {
    if (bloqueado) return;
    modal.value = {
        titulo: `¿Emitir la factura de ${props.pedido.numero}?`,
        mensaje: `Son ${money(props.pedido.total)} para ${props.contacto?.razon_social || props.contacto?.nombre_completo || 'el cliente'}.\n\n`
            + `· Se radica en la DIAN: ${sendDian.value ? 'SÍ, ahora mismo' : 'no por ahora'}\n`
            + `· Se le envía por correo: ${sendMail.value ? 'SÍ' : 'no'}\n\n`
            + 'Una factura electrónica radicada no se puede borrar: para deshacerla hay que emitir una nota crédito.',
        color: 'amber',
        textoConfirmar: 'Sí, emitir en SIIGO',
        onConfirmar: emitir,
    };
};
</script>

<template>
    <Head :title="`Facturar · ${pedido.numero}`"/>
    <AppLayout>
        <div class="space-y-5 max-w-5xl mx-auto">

            <Link href="/app/facturacion/bandeja" class="text-sm text-brand-600 hover:underline inline-flex items-center gap-1">
                <ArrowLeft class="h-4 w-4"/> Volver a la bandeja
            </Link>

            <!-- Header -->
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Receipt class="h-6 w-6 text-brand-600"/>
                        Facturar pedido {{ pedido.numero }}
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">
                        Revisá los datos. Si todo cuadra, decidí envío DIAN + mail y confirmá.
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase text-surface-500">Tipo</div>
                    <div class="text-xl font-bold" :class="pedido.tipo === 'credito' ? 'text-amber-700' : 'text-emerald-700'">
                        {{ pedido.tipo === 'credito' ? '💳 Crédito' : '💵 Contado' }}
                    </div>
                </div>
            </div>

            <!-- Advertencias -->
            <div v-if="advertencias.length" class="space-y-2">
                <div v-for="(w, i) in advertencias" :key="i"
                     :class="['card p-3 flex items-start gap-2 text-sm',
                              w.tipo === 'danger' ? 'bg-red-50 border-l-4 border-red-500 text-red-900' : 'bg-amber-50 border-l-4 border-amber-500 text-amber-900']">
                    <AlertTriangle class="h-5 w-5 shrink-0 mt-0.5"/>
                    <div>{{ w.msg }}</div>
                </div>
            </div>

            <!-- Grid cliente + pedido -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card p-4">
                    <h3 class="text-sm font-bold uppercase text-surface-500 mb-3">Cliente</h3>
                    <div v-if="contacto" class="space-y-1 text-sm">
                        <div class="text-lg font-bold">{{ contacto.nombre }}</div>
                        <div><span class="text-surface-500">NIT:</span> {{ contacto.nit }}</div>
                        <div v-if="contacto.email"><span class="text-surface-500">Email:</span> {{ contacto.email }}</div>
                        <div v-if="contacto.telefono"><span class="text-surface-500">Tel:</span> {{ contacto.telefono }}</div>
                        <div v-if="contacto.direccion"><span class="text-surface-500">Dir:</span> {{ contacto.direccion }}</div>
                        <div v-if="contacto.ciudad"><span class="text-surface-500">Ciudad:</span> {{ contacto.ciudad }}</div>
                    </div>
                    <div v-else class="text-sm text-red-600">⚠ Sin datos de cliente</div>
                </div>

                <div class="card p-4">
                    <h3 class="text-sm font-bold uppercase text-surface-500 mb-3">Pedido · trazabilidad</h3>
                    <div class="space-y-1 text-sm">
                        <div><span class="text-surface-500">Vendedor:</span> {{ pedido.vendedor || '—' }}</div>
                        <div><span class="text-surface-500">Alistador:</span> {{ pedido.alistador || '—' }}</div>
                        <div><span class="text-surface-500">Alistado:</span> {{ pedido.alistado_at }}</div>
                        <div><span class="text-surface-500">Ubicación origen:</span> {{ pedido.ubicacion || '—' }} <span v-if="pedido.ubicacion_codigo" class="text-xs text-surface-500">({{ pedido.ubicacion_codigo }})</span></div>
                        <div v-if="pedido.notas_cliente" class="mt-2 p-2 bg-surface-100 dark:bg-surface-800 rounded text-xs">
                            <b>Notas cliente:</b> {{ pedido.notas_cliente }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="card overflow-hidden">
                <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-800">
                    <h3 class="font-semibold flex items-center gap-2">
                        <FileText class="h-5 w-5 text-brand-600"/>
                        Líneas · {{ items.length }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table v-tabla-movil class="w-full text-sm">
                        <thead class="text-xs uppercase text-surface-500 bg-surface-50 dark:bg-surface-900">
                            <tr>
                                <th class="p-3 text-left">SKU</th>
                                <th class="p-3 text-left">Descripción</th>
                                <th class="p-3 text-center">Cant</th>
                                <th class="p-3 text-right">Precio</th>
                                <th class="p-3 text-right">IVA %</th>
                                <th class="p-3 text-right">Subtotal</th>
                                <th class="p-3 text-right">IVA</th>
                                <th class="p-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="(it, i) in items" :key="i">
                                <td class="p-3 font-mono text-xs">{{ it.sku }}</td>
                                <td class="p-3">{{ it.descripcion }}</td>
                                <td class="p-3 text-center">{{ it.cantidad }}</td>
                                <td class="p-3 text-right">{{ money(it.precio) }}</td>
                                <td class="p-3 text-right text-xs">{{ it.iva_pct }}%</td>
                                <td class="p-3 text-right">{{ money(it.subtotal) }}</td>
                                <td class="p-3 text-right text-xs">{{ money(it.iva_valor) }}</td>
                                <td class="p-3 text-right font-bold">{{ money(it.total) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-100 dark:bg-surface-900 font-bold">
                            <tr>
                                <td colspan="5" class="p-3 text-right">Subtotal</td>
                                <td class="p-3 text-right" colspan="2">{{ money(pedido.subtotal) }}</td>
                                <td class="p-3 text-right">—</td>
                            </tr>
                            <tr>
                                <td colspan="5" class="p-3 text-right">IVA</td>
                                <td class="p-3 text-right" colspan="2">{{ money(pedido.iva) }}</td>
                                <td class="p-3 text-right">—</td>
                            </tr>
                            <tr class="text-lg">
                                <td colspan="5" class="p-3 text-right">TOTAL</td>
                                <td class="p-3 text-right text-brand-700" colspan="2">{{ money(pedido.total) }}</td>
                                <td class="p-3 text-right">—</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Decisión + botón facturar -->
            <div class="card p-5 bg-gradient-to-br from-brand-500/5 to-emerald-500/5">
                <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                    <Send class="h-5 w-5 text-brand-600"/>
                    Decisión del facturador
                </h3>

                <div class="space-y-3 mb-4">
                    <label class="flex items-center gap-3 cursor-pointer p-3 bg-white dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 hover:ring-2 hover:ring-brand-400">
                        <input type="checkbox" v-model="sendDian" class="h-5 w-5 rounded"/>
                        <div class="flex-1">
                            <div class="font-semibold flex items-center gap-2">
                                <Send class="h-4 w-4 text-blue-600"/>
                                Enviar a DIAN inmediatamente ({{ sendDian ? 'SÍ' : 'NO' }})
                            </div>
                            <div class="text-xs text-surface-500">
                                Si lo dejás sin marcar, SIIGO guarda la factura pero no la radica · podés enviarla después.
                            </div>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer p-3 bg-white dark:bg-surface-900 rounded border border-surface-200 dark:border-surface-700 hover:ring-2 hover:ring-brand-400"
                           :class="{ 'opacity-50 cursor-not-allowed': !contacto?.email }">
                        <input type="checkbox" v-model="sendMail" :disabled="!contacto?.email" class="h-5 w-5 rounded"/>
                        <div class="flex-1">
                            <div class="font-semibold flex items-center gap-2">
                                <Mail class="h-4 w-4 text-indigo-600"/>
                                Enviar al email del cliente ({{ sendMail ? 'SÍ' : 'NO' }})
                            </div>
                            <div v-if="contacto?.email" class="text-xs text-surface-500">
                                Destino: <b>{{ contacto.email }}</b>
                            </div>
                            <div v-else class="text-xs text-red-600">
                                Cliente sin email · no se puede activar.
                            </div>
                        </div>
                    </label>
                </div>

                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div v-if="bloqueado" class="text-sm text-red-700 font-semibold flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5"/>
                        No se puede facturar · resolvé las advertencias rojas primero.
                    </div>
                    <div v-else class="text-sm text-surface-600">
                        ✓ Pedido listo para emitir. Esto crea la factura REAL en SIIGO.
                    </div>
                    <button @click="facturar" :disabled="bloqueado || confirmando"
                            class="btn-primary px-6 py-3 text-base font-bold"
                            :class="{ 'opacity-50 cursor-not-allowed': bloqueado || confirmando }">
                        <CheckCircle2 class="h-5 w-5"/>
                        {{ confirmando ? 'Facturando…' : 'Facturar en SIIGO' }}
                    </button>
                </div>
            </div>

        </div>

        <AppConfirmModal :cfg="modal" @cerrar="modal = null"/>
    </AppLayout>
</template>
