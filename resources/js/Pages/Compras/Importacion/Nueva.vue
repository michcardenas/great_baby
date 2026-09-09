<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Package, Globe } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useBogotaDate } from '@/composables/useBogotaDate';

const bog = useBogotaDate();

// Re-audit M2 UX-C1 · pantalla nueva. Antes Aracely no podía crear una
// Importación desde /app/compras — solo por Filament (admin).
const form = reactive({
    contenedor: '', bl_awb: '', proveedor_pais: '',
    puerto_origen: '', puerto_destino: 'Buenaventura',
    incoterm: 'FOB',
    moneda_origen: 'USD', tasa_cambio_liquidacion: 4000,
    fecha_zarpe: '', eta: '',
});
const enviando = ref(false);

const guardar = () => {
    if (enviando.value) return;
    enviando.value = true;
    router.post('/app/compras/importacion', form, {
        onFinish: () => enviando.value = false,
    });
};
</script>

<template>
    <Head title="Nueva importación"/>
    <AppLayout>
        <div class="max-w-3xl mx-auto space-y-4">
            <Link href="/app/compras?tab=importaciones" class="text-sm text-brand-600 hover:underline inline-flex items-center gap-1">
                <ArrowLeft class="h-4 w-4"/> Volver a Compras
            </Link>
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <Globe class="h-6 w-6 text-brand-600"/> Nueva importación
            </h1>
            <p class="text-sm text-surface-500">Registra el contenedor entrante. Después ligarás OCs, agregarás gastos capitalizables (flete/seguro/aduana) y liquidarás para prorratear al costo unitario.</p>

            <div v-if="$page.props.errors && Object.keys($page.props.errors).length" class="p-3 rounded-lg bg-red-50 dark:bg-red-950/40 border-l-4 border-red-500 text-red-700 dark:text-red-300 text-sm space-y-1">
                <div v-for="(msg, k) in $page.props.errors" :key="k">{{ msg }}</div>
            </div>

            <div class="card p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Contenedor</label>
                        <input v-model="form.contenedor" class="input w-full font-mono" placeholder="Ej: MSKU1234567" maxlength="50"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300" :title="'Bill of Lading (marítimo) / Air Waybill (aéreo)'">BL / AWB</label>
                        <input v-model="form.bl_awb" class="input w-full font-mono" placeholder="Ej: BL-45632" maxlength="50"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">País proveedor</label>
                        <input v-model="form.proveedor_pais" class="input w-full" placeholder="Ej: China" maxlength="100"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300" :title="'Cláusula de responsabilidad (Incoterms 2020): quién paga flete y seguro'">Incoterm</label>
                        <select v-model="form.incoterm" class="input w-full">
                            <option value="EXW">EXW · en fábrica</option>
                            <option value="FOB">FOB · libre a bordo</option>
                            <option value="CIF">CIF · costo + seguro + flete</option>
                            <option value="CFR">CFR · costo + flete</option>
                            <option value="DDP">DDP · entregado con impuestos</option>
                            <option value="DAP">DAP · entregado en destino</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Puerto origen</label>
                        <input v-model="form.puerto_origen" class="input w-full" placeholder="Ej: Shanghai" maxlength="100"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Puerto destino</label>
                        <input v-model="form.puerto_destino" class="input w-full" placeholder="Ej: Buenaventura" maxlength="100"/>
                    </div>
                </div>

                <div class="border-t border-surface-200 dark:border-surface-800 pt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Moneda</label>
                        <select v-model="form.moneda_origen" class="input w-full">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="CNY">CNY</option>
                            <option value="COP">COP</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300" :title="'Tasa que usará el sistema para convertir gastos en moneda extranjera a COP al liquidar'">Tasa de cambio (COP por 1 {{ form.moneda_origen }})</label>
                        <input type="number" step="0.01" v-model.number="form.tasa_cambio_liquidacion" class="input w-full font-mono" min="0"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Fecha de zarpe</label>
                        <input type="date" v-model="form.fecha_zarpe" class="input w-full"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300" :title="'Estimated Time of Arrival'">ETA (llegada estimada)</label>
                        <input type="date" v-model="form.eta" class="input w-full"/>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link href="/app/compras?tab=importaciones" class="btn-ghost">Cancelar</Link>
                <button @click="guardar" :disabled="enviando" class="btn-primary">
                    <Package class="h-4 w-4"/> {{ enviando ? 'Creando…' : 'Crear importación' }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
