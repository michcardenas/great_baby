<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Undo2, Search, Package, AlertCircle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    guiaBuscar: { type: String, default: '' },
    pedido: { type: Object, default: null },
});

const guia = ref(props.guiaBuscar);
const procesando = ref(false);
const form = reactive({
    destino_inventario: 'reingresa',
    notas: '',
});
const buscar = () => {
    router.get('/app/dropi/devolucion/registrar', { guia: guia.value }, { preserveState: false });
};
const guardar = () => {
    if (!props.pedido) return;
    procesando.value = true;
    router.post('/app/dropi/devolucion/registrar', {
        pedido_id: props.pedido.id,
        destino_inventario: form.destino_inventario,
        notas: form.notas,
    }, { onFinish: () => { procesando.value = false; } });
};
const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
</script>

<template>
    <Head title="Registrar devolución"/>
    <AppLayout>
        <div class="max-w-3xl mx-auto space-y-4">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <Undo2 class="h-6 w-6 text-brand-600"/>
                Registrar devolución
            </h1>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div class="card p-4">
                <label class="block text-xs font-semibold text-surface-600 mb-1">Guía o Dropi ID del pedido devuelto</label>
                <div class="flex gap-2">
                    <input v-model="guia" @keydown.enter="buscar" class="input flex-1 font-mono"
                        placeholder="Ej: 4200000123456 o DP-12345" autofocus/>
                    <button @click="buscar" class="btn-primary"><Search class="h-4 w-4"/> Buscar</button>
                </div>
            </div>

            <div v-if="guiaBuscar && !pedido" class="card p-6 text-center text-red-600">
                <AlertCircle class="h-8 w-8 mx-auto mb-2"/>
                No se encontró un pedido con esa guía.
            </div>

            <div v-if="pedido" class="card p-5 space-y-4">
                <div v-if="pedido.devolucion_existente" class="p-3 rounded-lg bg-amber-50 border-l-4 border-amber-500 text-amber-800 text-sm">
                    <AlertCircle class="h-4 w-4 inline"/> Este pedido ya tiene una devolución registrada.
                </div>

                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-xs text-surface-500">Guía</div>
                        <div class="font-mono font-bold text-lg">{{ pedido.guia }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-surface-500">Monto</div>
                        <div class="font-bold text-brand-600">{{ money(pedido.monto) }}</div>
                    </div>
                </div>
                <div class="border-t border-surface-200 pt-3">
                    <div class="text-sm font-semibold">{{ pedido.cliente }}</div>
                    <div class="text-xs text-surface-500">{{ pedido.ciudad }} · Estado: {{ pedido.estado }}</div>
                </div>

                <div>
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-2">Ítems del pedido</div>
                    <ul class="text-sm divide-y divide-surface-100">
                        <li v-for="(it, idx) in pedido.items" :key="idx" class="py-2 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-xs text-surface-500">{{ it.sku }}</div>
                                <div>{{ it.descripcion }}</div>
                            </div>
                            <div class="font-bold">{{ it.cantidad }}</div>
                        </li>
                    </ul>
                </div>

                <div v-if="!pedido.devolucion_existente" class="border-t border-surface-200 pt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-surface-600 mb-1">Destino inventario</label>
                        <div class="grid grid-cols-3 gap-2">
                            <label v-for="opt in [
                                {v:'reingresa', l:'♻ Re-ingresa', d:'Producto vuelve a stock vendible'},
                                {v:'averiado', l:'⚠ Averiado', d:'Va a bodega de dañados'},
                                {v:'perdido', l:'✖ Perdido', d:'Nunca llegó / se perdió'},
                            ]" :key="opt.v"
                                :class="['cursor-pointer border rounded-lg p-3 text-center text-sm',
                                    form.destino_inventario === opt.v ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-surface-200']">
                                <input type="radio" v-model="form.destino_inventario" :value="opt.v" class="hidden"/>
                                <div class="font-semibold">{{ opt.l }}</div>
                                <div class="text-xs text-surface-500 mt-1">{{ opt.d }}</div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-surface-600 mb-1">Notas (opcional)</label>
                        <textarea v-model="form.notas" rows="3" maxlength="500" class="input w-full"
                            placeholder="Ej: caja abierta, prenda con manchas, cliente rechazó tallaje"></textarea>
                    </div>
                    <button @click="guardar" :disabled="procesando" class="btn-primary w-full text-lg py-3 disabled:opacity-50">
                        <Package class="h-5 w-5"/>
                        {{ procesando ? 'Registrando…' : 'Registrar devolución' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
