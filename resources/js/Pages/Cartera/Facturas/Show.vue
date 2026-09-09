<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useMoney } from '@/composables/useMoney';
import { ArrowLeft, Download, Phone, Mail, MapPin, User, FileText, CreditCard, QrCode } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    factura: { type: Object, required: true },
    contacto: { type: Object, default: null },
    items: { type: Array, required: true },
    pagos: { type: Array, required: true },
});

const { money: fmtCOP } = useMoney();
const badgeEstado = {
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    danger: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    gray: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

const abrirWhatsApp = () => {
    if (! props.contacto?.telefono) return;
    const t = String(props.contacto.telefono).replace(/\D/g, '');
    if (t.length < 10) return;
    const num = t.startsWith('57') ? t : '57' + t;
    const msg = encodeURIComponent(`Hola ${props.contacto.nombre}, recordate del pago de la factura ${props.factura.numero} por ${fmtCOP(props.factura.saldo)}. Gracias.`);
    window.open(`https://wa.me/${num}?text=${msg}`, '_blank');
};

const totalPagado = props.pagos.reduce((s, p) => s + Number(p.monto_aplicado), 0);
const publicaUrl = typeof window !== 'undefined' && props.factura.token_publico
    ? `${window.location.origin}/factura/publica/${props.factura.token_publico}`
    : '';
const copiarUrl = async () => {
    try {
        await navigator.clipboard.writeText(publicaUrl);
        window.dispatchEvent(new CustomEvent('gb:ok', { detail: { mensaje: 'Enlace copiado' } }));
    } catch (e) {}
};
</script>

<template>
    <Head :title="`Factura ${factura.numero}`"/>
    <AppLayout>
        <div class="space-y-4 max-w-6xl">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <Link href="/app/facturas" class="text-sm text-surface-500 hover:text-brand-600 flex items-center gap-1 mb-2">
                        <ArrowLeft class="h-4 w-4"/> Volver a facturas
                    </Link>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <FileText class="h-6 w-6 text-brand-600"/>
                        Factura {{ factura.numero }}
                        <span :class="['ml-2 px-2 py-1 rounded text-xs font-bold', badgeEstado[factura.estado_color] || badgeEstado.gray]">
                            {{ factura.estado_label }}
                        </span>
                    </h1>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="`/cartera/factura/${factura.id}/pdf`" target="_blank" class="btn-secondary text-sm">
                        <Download class="h-4 w-4"/> PDF
                    </a>
                    <button v-if="contacto?.telefono && factura.saldo > 0" @click="abrirWhatsApp"
                            class="btn-primary text-sm bg-emerald-600 hover:bg-emerald-700">
                        <Phone class="h-4 w-4"/> Cobrar por WhatsApp
                    </button>
                </div>
            </div>

            <!-- Datos + Totales -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Cliente -->
                <div class="card p-4 lg:col-span-1">
                    <div class="text-xs uppercase tracking-wider text-surface-500 mb-2">Cliente</div>
                    <div v-if="contacto">
                        <Link :href="`/app/contactos/${contacto.id}`" class="text-lg font-bold text-brand-600 hover:underline flex items-center gap-2">
                            <User class="h-5 w-5"/> {{ contacto.nombre }}
                        </Link>
                        <div class="text-sm text-surface-600 dark:text-surface-400 mt-1">{{ contacto.documento }}</div>
                        <div v-if="contacto.telefono" class="text-sm mt-2 flex items-center gap-2">
                            <Phone class="h-4 w-4 text-surface-400"/> {{ contacto.telefono }}
                        </div>
                        <div v-if="contacto.email" class="text-sm mt-1 flex items-center gap-2">
                            <Mail class="h-4 w-4 text-surface-400"/> {{ contacto.email }}
                        </div>
                        <div v-if="contacto.direccion" class="text-sm mt-1 flex items-center gap-2 text-surface-500">
                            <MapPin class="h-4 w-4"/> {{ contacto.direccion }}<span v-if="contacto.ciudad">, {{ contacto.ciudad }}</span>
                        </div>
                    </div>
                    <div v-else class="text-sm text-surface-500">Sin datos de cliente</div>
                </div>

                <!-- Detalles factura -->
                <div class="card p-4 lg:col-span-1">
                    <div class="text-xs uppercase tracking-wider text-surface-500 mb-2">Fechas y ref</div>
                    <div class="text-sm space-y-1">
                        <div><span class="text-surface-500">Emisión:</span> {{ factura.fecha_emision }}</div>
                        <div><span class="text-surface-500">Vence:</span> {{ factura.fecha_vencimiento }}</div>
                        <div v-if="factura.emitida_at" class="text-xs text-surface-500 mt-2">Emitida electrónicamente el {{ new Date(factura.emitida_at).toLocaleString('es-CO') }}</div>
                        <div v-if="factura.numero_siigo" class="text-xs"><span class="text-surface-500">SIIGO:</span> {{ factura.numero_siigo }}</div>
                        <div v-if="factura.cufe" class="text-xs font-mono truncate mt-1"><span class="text-surface-500">CUFE:</span> {{ factura.cufe.slice(0, 30) }}…</div>
                    </div>
                </div>

                <!-- Totales -->
                <div class="card p-4 lg:col-span-1 bg-gradient-to-br from-brand-500/5 to-emerald-500/5">
                    <div class="text-xs uppercase tracking-wider text-surface-500 mb-2">Totales</div>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span>Subtotal</span><span class="font-mono">{{ fmtCOP(factura.subtotal) }}</span></div>
                        <div v-if="factura.descuento > 0" class="flex justify-between text-red-600"><span>Descuento</span><span class="font-mono">-{{ fmtCOP(factura.descuento) }}</span></div>
                        <div class="flex justify-between"><span>IVA</span><span class="font-mono">{{ fmtCOP(factura.impuestos) }}</span></div>
                        <div class="flex justify-between pt-2 border-t border-surface-200 dark:border-surface-800 font-bold text-brand-600">
                            <span>Total</span><span class="font-mono text-lg">{{ fmtCOP(factura.total) }}</span>
                        </div>
                        <div class="flex justify-between pt-1 font-bold" :class="factura.saldo > 0 ? 'text-red-600' : 'text-emerald-600'">
                            <span>Saldo pendiente</span><span class="font-mono">{{ fmtCOP(factura.saldo) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">Ítems ({{ items.length }})</div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-sm">
                        <thead>
                            <tr class="text-surface-500 text-xs uppercase border-b border-surface-200 dark:border-surface-800">
                                <th class="text-left py-2">Descripción</th>
                                <th class="text-right">Cant</th>
                                <th class="text-right">P. Unit</th>
                                <th class="text-right">Desc</th>
                                <th class="text-right">IVA</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="it in items" :key="it.id" class="border-b border-surface-100 dark:border-surface-900">
                                <td class="py-2">
                                    <div class="font-medium">{{ it.descripcion }}</div>
                                    <div v-if="it.referencia" class="text-xs text-surface-500 font-mono">{{ it.referencia }}</div>
                                </td>
                                <td class="text-right">{{ it.cantidad }}</td>
                                <td class="text-right font-mono">{{ fmtCOP(it.precio_unitario) }}</td>
                                <td class="text-right font-mono text-red-500">{{ it.descuento > 0 ? '-' + fmtCOP(it.descuento) : '—' }}</td>
                                <td class="text-right">{{ it.iva_porcentaje }}%</td>
                                <td class="text-right font-bold font-mono">{{ fmtCOP(it.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagos -->
            <div class="card p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs uppercase tracking-widest font-bold text-emerald-600 flex items-center gap-2">
                        <CreditCard class="h-4 w-4"/> Pagos aplicados ({{ pagos.length }})
                    </div>
                    <div class="text-sm text-surface-500">Total pagado: <span class="font-bold text-emerald-600">{{ fmtCOP(totalPagado) }}</span></div>
                </div>
                <div v-if="! pagos.length" class="text-center py-6 text-surface-500 text-sm">Sin pagos aplicados aún.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[500px] text-sm">
                        <thead>
                            <tr class="text-surface-500 text-xs uppercase border-b border-surface-200 dark:border-surface-800">
                                <th class="text-left py-2">Fecha</th>
                                <th class="text-left">Medio</th>
                                <th class="text-left">Ref</th>
                                <th class="text-right">Aplicado</th>
                                <th class="text-left">Por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in pagos" :key="p.id" class="border-b border-surface-100 dark:border-surface-900">
                                <td class="py-2">{{ p.fecha }}</td>
                                <td class="capitalize">{{ p.medio_pago || '—' }}</td>
                                <td class="font-mono text-xs text-surface-500">{{ p.referencia || '—' }}</td>
                                <td class="text-right font-bold font-mono text-emerald-600">{{ fmtCOP(p.monto_aplicado) }}</td>
                                <td class="text-xs text-surface-500">{{ p.registrado_por || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- QR portal público -->
            <div v-if="factura.qr_url || factura.token_publico" class="card p-4 bg-blue-500/5 border border-blue-500/25">
                <div class="text-xs uppercase tracking-widest font-bold text-blue-600 mb-2 flex items-center gap-2">
                    <QrCode class="h-4 w-4"/> Portal público del cliente
                </div>
                <div class="text-sm text-surface-500 mb-2">
                    Compartí este link con tu cliente para descargar la factura sin necesidad de crear cuenta:
                </div>
                <div v-if="publicaUrl" class="flex items-center gap-2">
                    <div class="font-mono text-xs bg-surface-100 dark:bg-surface-900 p-2 rounded break-all flex-1">{{ publicaUrl }}</div>
                    <button @click="copiarUrl" class="btn-secondary text-xs whitespace-nowrap">Copiar</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
