<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Wallet, Plus } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    movimientos: { type: Object, required: true },
});

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });

const modalNuevo = ref(false);
const form = reactive({
    fecha: new Date().toISOString().slice(0, 10),
    tipo: 'ajuste',
    monto: 0,
    pedido_id: null,
    categoria: 'ajuste_manual',
    dropi_movimiento_id: null,
});
const procesando = ref(false);
const crear = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post('/app/dropi/wallet', form, {
        preserveScroll: true,
        onSuccess: () => { modalNuevo.value = false; form.monto = 0; },
        onFinish: () => procesando.value = false,
    });
};
</script>

<template>
    <Head title="Wallet Dropi"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Wallet class="h-6 w-6 text-brand-600"/>
                    Wallet · movimientos
                </h1>
                <button @click="modalNuevo = true" class="btn-primary"><Plus class="h-4 w-4"/> Ajuste manual</button>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-3">Fecha</th>
                            <th class="text-left p-3">Tipo</th>
                            <th class="text-left p-3">Pedido</th>
                            <th class="text-left p-3">Categoría</th>
                            <th class="text-left p-3">Referencia</th>
                            <th class="text-right p-3">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        <tr v-for="m in movimientos.data" :key="m.id" class="hover:bg-surface-50">
                            <td class="p-3">{{ m.fecha }}</td>
                            <td class="p-3 text-xs">{{ m.tipo }}</td>
                            <td class="p-3 text-xs">{{ m.pedido_id ?? '—' }}</td>
                            <td class="p-3 text-xs">{{ m.categoria }}</td>
                            <td class="p-3 font-mono text-xs">{{ m.referencia }}</td>
                            <td class="p-3 text-right font-bold" :class="m.monto >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                {{ money(m.monto) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modalNuevo" @click.self="modalNuevo = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Ajuste manual wallet</h3>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="text-xs font-semibold">Fecha</label><input v-model="form.fecha" type="date" class="input w-full"/></div>
                        <div><label class="text-xs font-semibold">Tipo</label><input v-model="form.tipo" class="input w-full" placeholder="ingreso / egreso / ajuste"/></div>
                    </div>
                    <div><label class="text-xs font-semibold">Monto (positivo entra, negativo sale)</label><input type="number" step="0.01" v-model.number="form.monto" class="input w-full" autofocus/></div>
                    <div><label class="text-xs font-semibold">Pedido ID (opcional)</label><input type="number" v-model.number="form.pedido_id" class="input w-full"/></div>
                    <div><label class="text-xs font-semibold">Referencia Dropi (opcional)</label><input v-model="form.dropi_movimiento_id" class="input w-full"/></div>
                </div>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <button @click="modalNuevo = false" class="btn-ghost">Cancelar</button>
                    <button @click="crear" :disabled="procesando" class="btn-primary">{{ procesando ? '…' : 'Registrar' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
