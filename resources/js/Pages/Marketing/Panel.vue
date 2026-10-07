<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Camera, Warehouse, ArrowRightLeft, ArrowLeftRight } from 'lucide-vue-next';

const props = defineProps({
    mis_bodegas: { type: Array, required: true },
    prestamos: { type: Array, required: true },
    bodegas_comerciales: { type: Array, required: true },
});

const abiertos = computed(() => props.prestamos.filter(p => p.estado !== 'recibido' && p.estado !== 'anulado'));
const cerrados = computed(() => props.prestamos.filter(p => p.estado === 'recibido'));

const estadoChip = (e) => ({
    borrador: 'bg-surface-100 text-surface-700',
    en_transito: 'bg-amber-100 text-amber-900 ring-1 ring-amber-400',
    recibido: 'bg-emerald-100 text-emerald-800',
    anulado: 'bg-red-100 text-red-800',
}[e] || 'bg-surface-100');
</script>

<template>
    <Head title="Marketing · préstamos"/>
    <AppLayout>
        <div class="space-y-5 max-w-6xl mx-auto">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Camera class="h-6 w-6 text-brand-600"/>
                    Marketing · préstamos de bodega
                </h1>
                <p class="text-sm text-surface-500 mt-1">
                    Pedí productos prestados a bodega para fotos y videos, y devolvelos cuando termines.
                    Todo queda registrado en el kardex, sin papelitos.
                </p>
            </div>

            <!-- Nuestros almacenes + accesos rápidos -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="card p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <Warehouse class="h-4 w-4 text-brand-600"/>
                        <div class="text-sm font-semibold">Mis almacenes</div>
                    </div>
                    <div v-if="!mis_bodegas.length" class="text-xs text-surface-400 italic">
                        Pedile a Aracely que te cree una ubicación con prefijo MKT-.
                    </div>
                    <div v-else class="space-y-1 text-sm">
                        <div v-for="b in mis_bodegas" :key="b.id" class="flex items-center justify-between p-2 bg-surface-50 dark:bg-surface-900 rounded">
                            <div>
                                <div class="font-mono font-bold">{{ b.codigo }}</div>
                                <div class="text-xs text-surface-500">{{ b.nombre }}{{ b.ciudad ? ' · ' + b.ciudad : '' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card p-4 lg:col-span-2 space-y-3">
                    <div class="text-sm font-semibold mb-1">Acciones rápidas</div>
                    <Link href="/app/inventario/traslados" class="btn-primary w-full justify-start text-sm">
                        <ArrowRightLeft class="h-4 w-4"/>
                        📸 Pedir prestado a bodega · crear traslado
                    </Link>
                    <Link href="/app/inventario/traslados" class="btn-ghost border border-brand-300 w-full justify-start text-sm">
                        <ArrowLeftRight class="h-4 w-4"/>
                        ↩ Devolver a bodega · crear traslado de regreso
                    </Link>
                    <div class="text-[11px] text-surface-500 pt-1 italic">
                        Los traslados se piden desde el módulo de Inventario (botón "Nuevo").
                        Marcá origen=bodega comercial, destino=tu almacén MKT y motivo=prestamo.
                    </div>
                </div>
            </div>

            <!-- Prestado AHORA (no devuelto) -->
            <div class="card p-4">
                <div class="text-sm font-semibold mb-3 flex items-center gap-2">
                    🔴 Prestado ahora · {{ abiertos.length }}
                </div>
                <div v-if="!abiertos.length" class="text-sm text-surface-400 italic text-center py-6">
                    No hay préstamos abiertos.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="text-xs uppercase text-surface-500 border-b border-surface-200 dark:border-surface-800">
                        <tr>
                            <th class="p-2 text-left">N°</th>
                            <th class="p-2 text-left">De → A</th>
                            <th class="p-2 text-left">Motivo</th>
                            <th class="p-2 text-center">Items</th>
                            <th class="p-2 text-left">Solicitó</th>
                            <th class="p-2 text-left">Fecha</th>
                            <th class="p-2 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                        <tr v-for="p in abiertos" :key="p.id" class="hover:bg-surface-50 dark:hover:bg-surface-900">
                            <td class="p-2 font-mono text-xs">{{ p.numero }}</td>
                            <td class="p-2">
                                <span class="font-mono text-xs">{{ p.origen }}</span>
                                <span class="text-surface-400 mx-1">→</span>
                                <span class="font-mono text-xs">{{ p.destino }}</span>
                            </td>
                            <td class="p-2 text-xs">{{ p.motivo || '—' }}</td>
                            <td class="p-2 text-center">{{ p.items_count }}</td>
                            <td class="p-2 text-xs">{{ p.solicitado_por || '—' }}</td>
                            <td class="p-2 text-xs">{{ p.fecha }}</td>
                            <td class="p-2 text-center">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold" :class="estadoChip(p.estado)">{{ p.estado }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Devueltos (histórico) -->
            <div v-if="cerrados.length" class="card p-4">
                <div class="text-sm font-semibold mb-3">✓ Devueltos · histórico ({{ cerrados.length }})</div>
                <div class="divide-y divide-surface-200 dark:divide-surface-800 text-sm">
                    <div v-for="p in cerrados" :key="p.id" class="flex items-center justify-between py-2">
                        <div>
                            <div class="font-mono text-xs">{{ p.numero }} · {{ p.origen }} → {{ p.destino }}</div>
                            <div class="text-xs text-surface-500">{{ p.items_count }} items · devuelto {{ p.ejecutado_at || p.fecha }}</div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">OK</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
