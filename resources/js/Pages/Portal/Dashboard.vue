<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingBag, FileText, DollarSign, AlertCircle, PackageCheck } from 'lucide-vue-next';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    kpis: { type: Object, required: true },
    ultimos_pedidos: { type: Array, required: true },
});

const money = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0);

const badgeEstado = (e) => ({
    borrador: 'bg-surface-100 text-surface-700',
    enviado: 'bg-blue-100 text-blue-800',
    aprobado: 'bg-emerald-100 text-emerald-800',
    rechazado: 'bg-red-100 text-red-800',
    facturado: 'bg-brand-100 text-brand-800',
    anulado: 'bg-surface-200 text-surface-600',
}[e] || 'bg-surface-100 text-surface-700');
</script>

<template>
    <Head title="Portal · Inicio"/>
    <PortalLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold">Bienvenido, {{ $page.props.auth?.cliente?.nombre_display }}</h1>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><PackageCheck class="h-3 w-3"/> Pendientes</div>
                    <div class="text-3xl font-bold mt-1">{{ kpis.pedidos_pendientes }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><ShoppingBag class="h-3 w-3"/> Del mes</div>
                    <div class="text-3xl font-bold mt-1">{{ kpis.pedidos_mes }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><DollarSign class="h-3 w-3"/> Saldo</div>
                    <div class="text-2xl font-bold mt-1">{{ money(kpis.saldo) }}</div>
                </div>
                <div class="card p-4" :class="kpis.facturas_vencidas > 0 ? 'ring-2 ring-red-500' : ''">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-2"><AlertCircle class="h-3 w-3"/> Vencidas</div>
                    <div class="text-3xl font-bold mt-1" :class="kpis.facturas_vencidas > 0 ? 'text-red-600' : ''">
                        {{ kpis.facturas_vencidas }}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Link href="/portal/catalogo" class="card p-6 hover:shadow-lg transition-shadow group">
                    <ShoppingBag class="h-8 w-8 text-brand-600 mb-2"/>
                    <div class="font-bold text-lg group-hover:text-brand-700">Explorar catálogo</div>
                    <p class="text-sm text-surface-500 mt-1">Consulta productos y precios especiales asignados a tu cuenta.</p>
                </Link>
                <Link href="/portal/pedidos" class="card p-6 hover:shadow-lg transition-shadow group">
                    <FileText class="h-8 w-8 text-brand-600 mb-2"/>
                    <div class="font-bold text-lg group-hover:text-brand-700">Mis pedidos</div>
                    <p class="text-sm text-surface-500 mt-1">Estado de tus pedidos enviados, aprobados y facturados.</p>
                </Link>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600">Últimos pedidos</div>
                    <Link href="/portal/pedidos" class="text-xs text-brand-600 hover:underline">Ver todos →</Link>
                </div>
                <div v-if="!ultimos_pedidos.length" class="text-center py-8 text-surface-500 text-sm">
                    Aún no has hecho pedidos. Empieza a explorar el <Link href="/portal/catalogo" class="text-brand-600 hover:underline">catálogo</Link>.
                </div>
                <div v-else class="divide-y divide-surface-100 dark:divide-surface-800">
                    <Link v-for="p in ultimos_pedidos" :key="p.id"
                        :href="`/portal/pedidos/${p.id}`"
                        class="flex items-center justify-between py-3 hover:bg-surface-50 dark:hover:bg-surface-800 px-2 -mx-2 rounded">
                        <div>
                            <div class="font-semibold">{{ p.numero }}</div>
                            <div class="text-xs text-surface-500">{{ p.fecha }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold">{{ money(p.total) }}</div>
                            <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase mt-1', badgeEstado(p.estado)]">
                                {{ p.estado }}
                            </span>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>
