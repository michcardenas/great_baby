<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Package, Plus, Zap } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ importacion: { type: Object, required: true } });
const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });

const modalGasto = ref(false);
const gastoForm = reactive({
    concepto: 'flete', descripcion: '', moneda: 'COP', monto: 0,
    capitalizable: true, metodo_prorrateo: 'fob',
    factura_proveedor: '', fecha: new Date().toISOString().slice(0, 10),
});
const procesando = ref(false);
const agregarGasto = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post(`/app/compras/importacion/${props.importacion.id}/gasto`, gastoForm, {
        onSuccess: () => { modalGasto.value = false; Object.assign(gastoForm, { monto: 0, descripcion: '', factura_proveedor: '' }); },
        onFinish: () => procesando.value = false,
    });
};
const liquidar = () => {
    if (!confirm('Liquidar importación? Se prorratean todos los gastos y se congela el costo final por unidad.')) return;
    router.post(`/app/compras/importacion/${props.importacion.id}/liquidar`);
};
</script>

<template>
    <Head :title="`Importación ${importacion.numero}`"/>
    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-4">
            <Link href="/app/compras" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div class="card p-5">
                <div class="flex items-start justify-between flex-wrap gap-3">
                    <div>
                        <div class="text-xs text-surface-500 uppercase">Importación</div>
                        <h1 class="text-2xl font-bold font-mono">{{ importacion.numero }}</h1>
                        <div class="text-xs text-surface-500 mt-1">
                            Contenedor: {{ importacion.contenedor || '—' }} · BL/AWB: {{ importacion.bl_awb || '—' }} · Incoterm: {{ importacion.incoterm || '—' }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-surface-500 uppercase">Estado</div>
                        <span class="inline-block px-3 py-1 rounded bg-brand-100 text-brand-800 font-bold text-sm uppercase">{{ importacion.estado }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-sm border-t pt-3">
                    <div><b>Puerto origen:</b> {{ importacion.puerto_origen || '—' }}</div>
                    <div><b>Puerto destino:</b> {{ importacion.puerto_destino || '—' }}</div>
                    <div><b>Zarpe:</b> {{ importacion.fecha_zarpe || '—' }}</div>
                    <div><b>ETA:</b> {{ importacion.eta || '—' }}</div>
                    <div><b>Llegada:</b> {{ importacion.fecha_llegada || '—' }}</div>
                    <div><b>Liquidación:</b> {{ importacion.fecha_liquidacion || '—' }}</div>
                    <div><b>Moneda:</b> {{ importacion.moneda_origen }} @ {{ importacion.tasa_cambio_liquidacion }}</div>
                </div>
                <div class="flex items-center gap-2 border-t pt-3 mt-3">
                    <button v-if="importacion.estado !== 'liquidada' && importacion.estado !== 'cerrada'" @click="liquidar" class="btn-primary bg-emerald-600 hover:bg-emerald-700">
                        <Zap class="h-4 w-4"/> Liquidar contenedor
                    </button>
                    <a :href="`/compras/importacion/${importacion.id}/pdf`" target="_blank" class="btn-ghost">PDF</a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="card p-4"><div class="text-xs text-surface-500">Total FOB</div><div class="text-xl font-bold">{{ money(importacion.total_fob) }}</div></div>
                <div class="card p-4"><div class="text-xs text-surface-500">Total gastos</div><div class="text-xl font-bold">{{ money(importacion.total_gastos) }}</div></div>
                <div class="card p-4"><div class="text-xs text-surface-500">Costo total</div><div class="text-xl font-bold text-brand-600">{{ money(importacion.total_fob + importacion.total_gastos) }}</div></div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase font-bold text-brand-600">Gastos ({{ importacion.gastos.length }})</div>
                    <button @click="modalGasto = true" class="btn-ghost text-xs"><Plus class="h-3 w-3"/> Agregar gasto</button>
                </div>
                <table v-if="importacion.gastos.length" class="w-full text-sm">
                    <thead class="text-[10px] text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-2">Fecha</th>
                            <th class="text-left p-2">Concepto</th>
                            <th class="text-right p-2">Monto</th>
                            <th class="text-right p-2">Base COP</th>
                            <th class="text-center p-2">Capitaliza</th>
                            <th class="text-left p-2">Prorrateo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="g in importacion.gastos" :key="g.id">
                            <td class="p-2 text-xs">{{ g.fecha }}</td>
                            <td class="p-2">{{ g.concepto }}</td>
                            <td class="p-2 text-right">{{ g.moneda }} {{ money(g.monto) }}</td>
                            <td class="p-2 text-right font-bold">{{ money(g.monto_base) }}</td>
                            <td class="p-2 text-center">{{ g.capitalizable ? '✓' : '—' }}</td>
                            <td class="p-2 text-xs">{{ g.metodo_prorrateo }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-center py-4 text-surface-500 text-sm">Sin gastos aún.</p>
            </div>

            <div v-if="importacion.lineas.length" class="card p-4">
                <div class="text-xs uppercase font-bold text-brand-600 mb-2">Líneas ({{ importacion.lineas.length }})</div>
                <table class="w-full text-sm">
                    <thead class="text-[10px] text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-right p-2">Cant</th>
                            <th class="text-right p-2">FOB unit</th>
                            <th class="text-right p-2">FOB total</th>
                            <th class="text-right p-2">Prorrateado</th>
                            <th class="text-right p-2">Costo final</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="l in importacion.lineas" :key="l.id">
                            <td class="p-2 text-right">{{ l.cantidad }}</td>
                            <td class="p-2 text-right">{{ money(l.costo_fob_unit) }}</td>
                            <td class="p-2 text-right">{{ money(l.costo_fob_total) }}</td>
                            <td class="p-2 text-right text-amber-600">{{ money(l.gasto_prorrateado) }}</td>
                            <td class="p-2 text-right font-bold text-brand-600">{{ money(l.costo_final_unit) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modalGasto" @click.self="modalGasto = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Agregar gasto de importación</h3>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="text-xs font-semibold">Concepto</label>
                            <select v-model="gastoForm.concepto" class="input w-full">
                                <option value="flete">Flete</option>
                                <option value="seguro">Seguro</option>
                                <option value="agencia">Agencia aduanera</option>
                                <option value="arancel">Arancel</option>
                                <option value="iva_importacion">IVA importación</option>
                                <option value="bodegaje">Bodegaje</option>
                                <option value="transporte_interno">Transporte interno</option>
                                <option value="otros">Otros</option>
                            </select>
                        </div>
                        <div><label class="text-xs font-semibold">Fecha</label><input type="date" v-model="gastoForm.fecha" class="input w-full"/></div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div><label class="text-xs font-semibold">Moneda</label>
                            <select v-model="gastoForm.moneda" class="input w-full">
                                <option>COP</option><option>USD</option><option>EUR</option><option>CNY</option>
                            </select>
                        </div>
                        <div class="col-span-2"><label class="text-xs font-semibold">Monto</label><input type="number" step="0.01" v-model.number="gastoForm.monto" class="input w-full" autofocus/></div>
                    </div>
                    <div><label class="text-xs font-semibold">Método prorrateo</label>
                        <select v-model="gastoForm.metodo_prorrateo" class="input w-full">
                            <option value="fob">Por valor FOB</option>
                            <option value="cantidad">Por cantidad</option>
                            <option value="volumen">Por volumen</option>
                        </select>
                    </div>
                    <div><label class="text-xs font-semibold">Factura</label><input v-model="gastoForm.factura_proveedor" class="input w-full"/></div>
                    <div><label class="text-xs font-semibold">Descripción</label><input v-model="gastoForm.descripcion" class="input w-full"/></div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="gastoForm.capitalizable"/> Capitalizable (suma al costo)</label>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modalGasto = false" class="btn-ghost">Cancelar</button>
                    <button @click="agregarGasto" :disabled="procesando" class="btn-primary">{{ procesando ? '…' : 'Agregar' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
