<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { FileText, ArrowLeft, Download, CheckCircle, DollarSign, Calendar, ShieldCheck } from 'lucide-vue-next';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { useMoney } from '@/composables/useMoney';

const props = defineProps({ factura: { type: Object, required: true } });
const { money } = useMoney();

const badgeEstado = (e) => ({
    pendiente: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    pagada: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    abonada: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200',
    vencida: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
}[e] || 'bg-surface-100 dark:bg-surface-800');
</script>

<template>
    <Head :title="`Factura ${factura.numero}`"/>
    <PortalLayout>
        <div class="space-y-4">
            <Link href="/portal/facturas" class="text-sm text-brand-600 hover:underline inline-flex items-center gap-1">
                <ArrowLeft class="h-4 w-4"/> Volver a mis facturas
            </Link>

            <div class="card p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold flex items-center gap-2">
                            <FileText class="h-6 w-6 text-brand-600"/>
                            Factura {{ factura.numero }}
                        </h1>
                        <div class="mt-2 flex items-center gap-3 text-sm text-surface-600 dark:text-surface-300">
                            <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase', badgeEstado(factura.estado)]">{{ factura.estado }}</span>
                            <span v-if="factura.es_electronica" class="inline-flex items-center gap-1 text-xs text-emerald-700 dark:text-emerald-300">
                                <ShieldCheck class="h-3 w-3"/> Electrónica DIAN
                            </span>
                        </div>
                    </div>
                    <a :href="factura.pdf_url" target="_blank" rel="noopener" class="btn-primary">
                        <Download class="h-4 w-4"/> Descargar PDF
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 text-sm">
                    <div>
                        <div class="text-xs uppercase text-surface-500 dark:text-surface-400 flex items-center gap-1"><Calendar class="h-3 w-3"/> Emisión</div>
                        <div class="mt-1 font-semibold">{{ factura.emision }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-surface-500 dark:text-surface-400 flex items-center gap-1"><Calendar class="h-3 w-3"/> Vence</div>
                        <div class="mt-1 font-semibold">{{ factura.vence }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-surface-500 dark:text-surface-400 flex items-center gap-1"><DollarSign class="h-3 w-3"/> Total</div>
                        <div class="mt-1 font-semibold">{{ money(factura.total) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-surface-500 dark:text-surface-400 flex items-center gap-1"><DollarSign class="h-3 w-3"/> Saldo</div>
                        <div class="mt-1 font-bold" :class="factura.saldo > 0 ? 'text-red-600' : 'text-emerald-600'">{{ money(factura.saldo) }}</div>
                    </div>
                </div>

                <div v-if="factura.cufe" class="mt-4 text-[11px] font-mono break-all bg-surface-50 dark:bg-surface-900/50 rounded p-2">
                    <span class="text-surface-500">CUFE:</span> {{ factura.cufe }}
                </div>
            </div>

            <div class="card overflow-x-auto">
                <div class="p-4 border-b border-surface-200 dark:border-surface-800 font-semibold">Ítems</div>
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 dark:text-surface-400 uppercase border-b border-surface-200 dark:border-surface-800">
                        <tr>
                            <th class="text-left p-3">Descripción</th>
                            <th class="text-left p-3">Ref.</th>
                            <th class="text-right p-3">Cant.</th>
                            <th class="text-right p-3">P. Unit</th>
                            <th class="text-right p-3">Dscto</th>
                            <th class="text-right p-3">IVA</th>
                            <th class="text-right p-3">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                        <tr v-for="(it, i) in factura.items" :key="i">
                            <td class="p-3">{{ it.descripcion }}</td>
                            <td class="p-3 font-mono text-xs text-surface-500">{{ it.referencia || '—' }}</td>
                            <td class="p-3 text-right">{{ it.cantidad }}</td>
                            <td class="p-3 text-right">{{ money(it.precio_unit) }}</td>
                            <td class="p-3 text-right">{{ it.descuento_pct }}%</td>
                            <td class="p-3 text-right">{{ it.impuesto_pct }}%</td>
                            <td class="p-3 text-right font-semibold">{{ money(it.subtotal) }}</td>
                        </tr>
                        <tr v-if="!factura.items.length"><td colspan="7" class="p-6 text-center text-surface-500">Sin ítems.</td></tr>
                    </tbody>
                    <tfoot class="border-t border-surface-200 dark:border-surface-800 text-sm">
                        <tr><td colspan="6" class="p-2 text-right text-surface-600">Subtotal</td><td class="p-2 text-right">{{ money(factura.subtotal) }}</td></tr>
                        <tr><td colspan="6" class="p-2 text-right text-surface-600">Descuento</td><td class="p-2 text-right">− {{ money(factura.descuento) }}</td></tr>
                        <tr><td colspan="6" class="p-2 text-right text-surface-600">Impuestos</td><td class="p-2 text-right">{{ money(factura.impuestos) }}</td></tr>
                        <tr class="font-bold"><td colspan="6" class="p-2 text-right">TOTAL</td><td class="p-2 text-right">{{ money(factura.total) }}</td></tr>
                    </tfoot>
                </table>
            </div>

            <div class="card overflow-x-auto">
                <div class="p-4 border-b border-surface-200 dark:border-surface-800 font-semibold flex items-center gap-2">
                    <CheckCircle class="h-4 w-4 text-emerald-500"/> Pagos aplicados
                </div>
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 dark:text-surface-400 uppercase border-b border-surface-200 dark:border-surface-800">
                        <tr>
                            <th class="text-left p-3">Fecha</th>
                            <th class="text-left p-3">Medio</th>
                            <th class="text-left p-3">Referencia</th>
                            <th class="text-right p-3">Monto aplicado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                        <tr v-for="(p, i) in factura.pagos" :key="i">
                            <td class="p-3">{{ p.fecha }}</td>
                            <td class="p-3 uppercase text-xs">{{ p.medio }}</td>
                            <td class="p-3 font-mono text-xs">{{ p.referencia || '—' }}</td>
                            <td class="p-3 text-right font-semibold text-emerald-700 dark:text-emerald-400">{{ money(p.monto) }}</td>
                        </tr>
                        <tr v-if="!factura.pagos.length"><td colspan="4" class="p-6 text-center text-surface-500">Aún sin pagos registrados.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-if="factura.observaciones" class="card p-4 text-sm">
                <div class="text-xs uppercase text-surface-500 mb-1">Observaciones</div>
                <p class="whitespace-pre-line">{{ factura.observaciones }}</p>
            </div>
        </div>
    </PortalLayout>
</template>
