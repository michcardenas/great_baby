<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ShieldCheck, Upload, Save } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = reactive({
    cliente_nombre: '', cliente_telefono: '', pedido_guia: '',
    variante_id: null, cantidad: 1, descripcion_falla: '', fotos: [],
});
const procesando = ref(false);
const errorMsg = ref('');
const onFotos = (e) => { form.fotos = Array.from(e.target.files).slice(0, 5); };

const guardar = () => {
    if (procesando.value) return;
    procesando.value = true;
    errorMsg.value = '';
    router.post('/app/garantias', form, {
        forceFormData: true,
        onError: (e) => { errorMsg.value = Object.values(e).flat()[0] || 'Error'; },
        onFinish: () => procesando.value = false,
    });
};
</script>

<template>
    <Head title="Nueva garantía"/>
    <AppLayout>
        <div class="max-w-3xl mx-auto space-y-4">
            <Link href="/app/garantias" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>
            <h1 class="text-2xl font-bold flex items-center gap-2"><ShieldCheck class="h-6 w-6 text-brand-600"/>Nueva garantía</h1>

            <div v-if="errorMsg" class="p-3 rounded-lg bg-red-50 border-l-4 border-red-500 text-red-700 text-sm">{{ errorMsg }}</div>

            <div class="card p-5 space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><label class="text-xs font-semibold">Cliente</label><input v-model="form.cliente_nombre" class="input w-full" required autofocus/></div>
                    <div><label class="text-xs font-semibold">Teléfono</label><input v-model="form.cliente_telefono" class="input w-full" placeholder="300..."/></div>
                    <div><label class="text-xs font-semibold">Guía del pedido original (opcional)</label><input v-model="form.pedido_guia" class="input w-full font-mono" placeholder="Ej: 4200000..."/></div>
                    <div><label class="text-xs font-semibold">Cantidad</label><input type="number" min="1" v-model.number="form.cantidad" class="input w-full"/></div>
                </div>
                <div>
                    <label class="text-xs font-semibold">Descripción de la falla</label>
                    <textarea v-model="form.descripcion_falla" rows="4" class="input w-full" minlength="10" required placeholder="Describe la falla reportada por el cliente..."></textarea>
                </div>
                <div>
                    <label class="text-xs font-semibold">Fotos de evidencia (máx 5, 5MB c/u)</label>
                    <input type="file" accept="image/*" multiple @change="onFotos" class="input w-full"/>
                    <p v-if="form.fotos.length" class="text-xs text-surface-500 mt-1">{{ form.fotos.length }} archivos seleccionados</p>
                </div>
            </div>

            <button @click="guardar" :disabled="procesando" class="btn-primary w-full">
                <Save class="h-4 w-4"/> {{ procesando ? 'Guardando…' : 'Crear ticket de garantía' }}
            </button>
        </div>
    </AppLayout>
</template>
