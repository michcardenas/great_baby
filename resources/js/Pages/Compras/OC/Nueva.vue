<script setup>
import { reactive, ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ShoppingCart, Plus, Trash2, Save } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ proveedores: { type: Array, required: true } });

const form = reactive({
    proveedor_id: '',
    tipo: 'nacional',
    moneda: 'COP',
    tasa_cambio: 1,
    fecha_esperada: '',
    observaciones: '',
    items: [{ descripcion: '', cantidad: 1, precio_unit: 0, iva_pct: 19, descuento_pct: 0 }],
});
const procesando = ref(false);

const addItem = () => form.items.push({ descripcion: '', cantidad: 1, precio_unit: 0, iva_pct: 19, descuento_pct: 0 });
const rmItem = (i) => form.items.splice(i, 1);

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
const subtotal = computed(() => form.items.reduce((s, i) => s + (i.cantidad * i.precio_unit * (1 - (i.descuento_pct || 0) / 100)), 0));
const iva = computed(() => form.items.reduce((s, i) => {
    const sub = i.cantidad * i.precio_unit * (1 - (i.descuento_pct || 0) / 100);
    return s + sub * ((i.iva_pct || 0) / 100);
}, 0));
const total = computed(() => subtotal.value + iva.value);

const guardar = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post('/app/compras/oc', form, {
        onError: () => { procesando.value = false; },
        onSuccess: () => { procesando.value = false; },
    });
};
</script>

<template>
    <Head title="Nueva orden de compra"/>
    <AppLayout>
        <div class="max-w-5xl mx-auto space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <ShoppingCart class="h-6 w-6 text-brand-600"/>
                Nueva orden de compra
            </h1>

            <div class="card p-5 space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold">Proveedor</label>
                        <select v-model="form.proveedor_id" class="input w-full" required>
                            <option value="">— Seleccionar —</option>
                            <option v-for="p in proveedores" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Tipo</label>
                        <select v-model="form.tipo" class="input w-full">
                            <option value="nacional">Nacional</option>
                            <option value="importacion">Importación</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Moneda</label>
                        <select v-model="form.moneda" class="input w-full">
                            <option value="COP">COP</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="CNY">CNY</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Tasa cambio</label>
                        <input type="number" step="0.01" v-model.number="form.tasa_cambio" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold">Fecha esperada</label>
                        <input type="date" v-model="form.fecha_esperada" class="input w-full"/>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold">Observaciones</label>
                    <textarea v-model="form.observaciones" rows="2" class="input w-full"></textarea>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600">Ítems</div>
                    <button @click="addItem" class="btn-ghost text-xs"><Plus class="h-3 w-3"/> Agregar ítem</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-[10px] text-surface-500 uppercase">
                            <tr>
                                <th class="text-left p-1">Descripción</th>
                                <th class="text-right p-1">Cant</th>
                                <th class="text-right p-1">Precio</th>
                                <th class="text-right p-1">Desc%</th>
                                <th class="text-right p-1">IVA%</th>
                                <th class="text-right p-1">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(it, i) in form.items" :key="i" class="border-t">
                                <td class="p-1"><input v-model="it.descripcion" class="input w-full text-sm" placeholder="Producto..."/></td>
                                <td class="p-1"><input type="number" step="0.001" v-model.number="it.cantidad" class="input w-20 text-right text-sm"/></td>
                                <td class="p-1"><input type="number" step="0.01" v-model.number="it.precio_unit" class="input w-28 text-right text-sm"/></td>
                                <td class="p-1"><input type="number" step="0.01" v-model.number="it.descuento_pct" class="input w-16 text-right text-sm"/></td>
                                <td class="p-1"><input type="number" step="0.01" v-model.number="it.iva_pct" class="input w-16 text-right text-sm"/></td>
                                <td class="p-1 text-right font-bold">{{ money(it.cantidad * it.precio_unit * (1 - (it.descuento_pct || 0) / 100) * (1 + (it.iva_pct || 0) / 100)) }}</td>
                                <td class="p-1"><button v-if="form.items.length > 1" @click="rmItem(i)" class="text-red-600"><Trash2 class="h-4 w-4"/></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-right pt-3 border-t mt-3 space-y-1">
                    <div class="text-sm text-surface-500">Subtotal: <b>{{ money(subtotal) }}</b></div>
                    <div class="text-sm text-surface-500">IVA: <b>{{ money(iva) }}</b></div>
                    <div class="text-xl font-bold text-brand-600">Total: {{ money(total) }}</div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link href="/app/compras" class="btn-ghost">Cancelar</Link>
                <button @click="guardar" :disabled="procesando || !form.proveedor_id" class="btn-primary disabled:opacity-50">
                    <Save class="h-4 w-4"/> {{ procesando ? 'Creando…' : 'Crear OC' }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
