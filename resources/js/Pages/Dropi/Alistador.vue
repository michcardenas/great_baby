<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Package, Truck, CheckCircle, Boxes } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    modo: { type: String, required: true },
    corte: { type: Object, default: null },
    recoleccion: { type: Array, required: true },
    empaque: { type: Array, required: true },
});

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });

const cambiarModo = (m) => router.get('/app/dropi/alistador', { modo: m }, { preserveState: false });

const tomar = (id) => router.post(`/app/dropi/alistador/pedido/${id}/tomar`, {}, { preserveScroll: true });
const empacar = (id) => router.post(`/app/dropi/alistador/pedido/${id}/empacar`, {}, { preserveScroll: true });
const despachar = (id) => router.post(`/app/dropi/alistador/pedido/${id}/despachar`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Vista del Alistador"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Boxes class="h-6 w-6 text-brand-600"/>
                        Vista del Alistador
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">
                        Corte activo: <b>{{ corte?.numero ?? '—' }}</b> · {{ corte?.fecha ?? '' }}
                    </p>
                </div>
                <div class="card p-1 flex">
                    <button @click="cambiarModo('recoleccion')"
                        :class="['px-4 py-2 rounded text-sm font-semibold',
                            modo === 'recoleccion' ? 'bg-brand-600 text-white' : 'text-surface-600']">
                        🛒 Recolección
                    </button>
                    <button @click="cambiarModo('empaque')"
                        :class="['px-4 py-2 rounded text-sm font-semibold',
                            modo === 'empaque' ? 'bg-brand-600 text-white' : 'text-surface-600']">
                        📦 Empaque
                    </button>
                </div>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <!-- Modo recolección: totales por producto -->
            <div v-if="modo === 'recoleccion'" class="card p-5">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">
                    Lista de recolección — recorre bodega una sola vez
                </div>
                <div v-if="!recoleccion.length" class="text-center py-8 text-surface-500 text-sm">
                    Nada por recolectar en el corte actual.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-surface-500 uppercase border-b border-surface-200 dark:border-surface-800">
                            <tr>
                                <th class="text-left p-3">SKU</th>
                                <th class="text-left p-3">Producto</th>
                                <th class="text-left p-3">Variante</th>
                                <th class="text-right p-3">Total unidades</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                            <tr v-for="r in recoleccion" :key="r.sku">
                                <td class="p-3 font-mono text-xs">{{ r.sku }}</td>
                                <td class="p-3">
                                    <div class="font-medium">{{ r.producto }}</div>
                                    <div class="text-xs text-surface-500">{{ r.referencia }}</div>
                                </td>
                                <td class="p-3 text-xs">{{ r.variante }}</td>
                                <td class="p-3 text-right font-bold text-brand-600 text-lg">{{ r.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modo empaque: cola de pedidos -->
            <div v-if="modo === 'empaque'" class="card p-5">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">
                    Cola de empaque compartida ({{ empaque.length }})
                </div>
                <div v-if="!empaque.length" class="text-center py-8 text-surface-500 text-sm">
                    Sin pedidos pendientes de empacar (o todos tomados por otros alistadores).
                </div>
                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div v-for="p in empaque" :key="p.id" class="border border-surface-200 dark:border-surface-800 rounded-lg p-4 hover:shadow-md">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <div class="text-xs text-surface-500">Guía</div>
                                <div class="font-mono font-bold">{{ p.guia }}</div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-100 text-blue-800">{{ p.estado }}</span>
                        </div>
                        <div class="text-sm font-medium">{{ p.cliente }}</div>
                        <div class="text-xs text-surface-500">{{ p.ciudad }}</div>
                        <div class="text-sm font-bold text-brand-600 mt-1">{{ money(p.monto) }}</div>
                        <div class="mt-3 flex items-center gap-2">
                            <button v-if="p.estado === 'pending'" @click="tomar(p.id)" class="btn-primary flex-1 text-xs">
                                <Package class="h-3 w-3"/> Tomar
                            </button>
                            <button v-if="p.estado === 'alistando'" @click="empacar(p.id)" class="btn-primary flex-1 text-xs">
                                <CheckCircle class="h-3 w-3"/> Empacar
                            </button>
                            <button v-if="p.estado === 'empacado'" @click="despachar(p.id)" class="btn-primary flex-1 text-xs bg-emerald-600 hover:bg-emerald-700">
                                <Truck class="h-3 w-3"/> Despachar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
