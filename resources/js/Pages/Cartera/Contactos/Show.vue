<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, User, Phone, Mail, MapPin, FileText, TrendingUp, Wallet } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    contacto: { type: Object, required: true },
    facturas: { type: Array, required: true },
    metricas: { type: Object, required: true },
});

const fmtCOP = (n) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO');

const rolLabel = {
    cliente: 'Cliente',
    b2b: 'B2B',
    proveedor: 'Proveedor',
    empleado: 'Empleado',
    vendedor: 'Vendedor Dropi',
};
const rolesActivos = Object.entries(props.contacto.roles).filter(([, v]) => v).map(([k]) => rolLabel[k]);

const badgeEstado = {
    warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    danger: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    gray: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
};

const abrirWA = () => {
    if (! props.contacto.telefono) return;
    const t = String(props.contacto.telefono).replace(/\D/g, '');
    const num = t.startsWith('57') ? t : '57' + t;
    window.open(`https://wa.me/${num}`, '_blank');
};
</script>

<template>
    <Head :title="contacto.nombre"/>
    <AppLayout>
        <div class="space-y-4 max-w-6xl">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <Link href="/app/contactos" class="text-sm text-surface-500 hover:text-brand-600 flex items-center gap-1 mb-2">
                        <ArrowLeft class="h-4 w-4"/> Volver a contactos
                    </Link>
                    <h1 class="text-2xl font-bold flex items-center gap-2 text-surface-900 dark:text-surface-100">
                        <User class="h-6 w-6 text-brand-600"/>
                        {{ contacto.nombre }}
                        <span v-if="!contacto.activo" class="text-xs uppercase text-red-600 bg-red-50 dark:bg-red-950/30 px-2 py-0.5 rounded">Inactivo</span>
                    </h1>
                    <div class="text-sm text-surface-500 font-mono mt-1">{{ contacto.documento }}</div>
                    <div class="flex flex-wrap gap-1 mt-2">
                        <span v-for="r in rolesActivos" :key="r"
                              class="text-xs font-bold uppercase px-2 py-0.5 rounded bg-brand-50 text-brand-800 dark:bg-brand-900/30 dark:text-brand-300">
                            {{ r }}
                        </span>
                    </div>
                </div>
                <button v-if="contacto.telefono" @click="abrirWA" class="btn-primary bg-emerald-600 hover:bg-emerald-700 text-sm">
                    <Phone class="h-4 w-4"/> WhatsApp
                </button>
            </div>

            <!-- Datos + KPIs -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="card p-4 lg:col-span-1">
                    <div class="text-xs uppercase tracking-wider text-surface-500 mb-3">Contacto</div>
                    <div class="space-y-2 text-sm">
                        <div v-if="contacto.telefono" class="flex items-center gap-2"><Phone class="h-4 w-4 text-surface-400"/> {{ contacto.telefono }}</div>
                        <div v-if="contacto.email" class="flex items-center gap-2"><Mail class="h-4 w-4 text-surface-400"/> <span class="truncate">{{ contacto.email }}</span></div>
                        <div v-if="contacto.direccion || contacto.ciudad" class="flex items-start gap-2 text-surface-500">
                            <MapPin class="h-4 w-4 flex-shrink-0 mt-0.5"/>
                            <div>
                                <div v-if="contacto.direccion">{{ contacto.direccion }}</div>
                                <div v-if="contacto.ciudad">{{ contacto.ciudad }}<span v-if="contacto.departamento">, {{ contacto.departamento }}</span></div>
                            </div>
                        </div>
                        <div v-if="contacto.regimen_iva" class="pt-2 text-xs text-surface-500">
                            Régimen: <span class="font-medium">{{ contacto.regimen_iva }}</span>
                        </div>
                        <div v-if="contacto.siigo_id" class="text-xs text-surface-500">
                            SIIGO: <span class="font-mono">{{ contacto.siigo_id }}</span>
                        </div>
                    </div>
                </div>
                <KpiCard label="Saldo pendiente" :value="metricas.saldo_pendiente" color="red" format="money" :icon="Wallet"/>
                <KpiCard label="Facturado histórico" :value="metricas.facturado_historico" color="emerald" format="money" :icon="TrendingUp"
                         :subtitle="metricas.facturas_count + ' facturas'"/>
            </div>

            <!-- Facturas -->
            <div class="card p-4">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3 flex items-center gap-2">
                    <FileText class="h-4 w-4"/> Últimas facturas ({{ facturas.length }})
                </div>
                <div v-if="! facturas.length" class="text-center py-6 text-surface-500 text-sm">Este contacto no tiene facturas registradas.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[500px] text-sm">
                        <thead>
                            <tr class="text-surface-500 text-xs uppercase border-b border-surface-200 dark:border-surface-800">
                                <th class="text-left py-2">Número</th>
                                <th class="text-right">Emisión</th>
                                <th class="text-right">Vence</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Saldo</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="f in facturas" :key="f.id" class="border-b border-surface-100 dark:border-surface-900 hover:bg-surface-50 dark:hover:bg-surface-900/30">
                                <td class="py-2 font-mono text-brand-600">
                                    <Link :href="'/app/facturas/' + f.id" class="hover:underline font-semibold">{{ f.numero }}</Link>
                                </td>
                                <td class="text-right text-surface-500">{{ f.fecha }}</td>
                                <td class="text-right text-surface-500">{{ f.vence }}</td>
                                <td class="text-right font-mono font-bold">{{ fmtCOP(f.total) }}</td>
                                <td class="text-right font-mono font-bold" :class="f.saldo > 0 ? 'text-red-600' : 'text-emerald-600'">{{ fmtCOP(f.saldo) }}</td>
                                <td class="text-center">
                                    <span :class="['inline-block px-2 py-0.5 rounded text-xs font-bold', badgeEstado[f.estado_color] || badgeEstado.gray]">{{ f.estado_label }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
