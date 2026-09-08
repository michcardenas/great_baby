<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Star, Plus, Trash2, Save } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ producto: { type: Object, required: true } });

const form = reactive({
    copy_comercial: props.producto.copy_comercial || '',
    specs: (Array.isArray(props.producto.specs) ? props.producto.specs : []).length ? props.producto.specs : [{ k: '', v: '' }],
    keywords_seo: props.producto.keywords_seo || '',
    beneficios: props.producto.beneficios || '',
});
const procesando = ref(false);

const addSpec = () => form.specs.push({ k: '', v: '' });
const rmSpec = (i) => form.specs.splice(i, 1);

const guardar = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post(`/app/marketing/producto/${props.producto.id}/ficha`, {
        _method: 'put',
        copy_comercial: form.copy_comercial,
        specs: form.specs.filter(s => s.k && s.v),
        keywords_seo: form.keywords_seo,
        beneficios: form.beneficios,
    }, { preserveScroll: true, onFinish: () => procesando.value = false });
};
</script>

<template>
    <Head :title="`Ficha marketing · ${producto.nombre}`"/>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-4">
            <Link href="/app/catalogo" class="btn-ghost inline-flex items-center gap-1 text-sm"><ArrowLeft class="h-4 w-4"/> Volver al catálogo</Link>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">{{ $page.props.flash.success }}</div>

            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2"><Star class="h-6 w-6 text-brand-600"/>Ficha marketing</h1>
                    <div class="text-sm text-surface-500 mt-1">{{ producto.referencia }} · <b>{{ producto.nombre }}</b></div>
                </div>
                <button @click="guardar" :disabled="procesando" class="btn-primary">
                    <Save class="h-4 w-4"/> {{ procesando ? 'Guardando…' : 'Guardar ficha' }}
                </button>
            </div>

            <div class="card p-4">
                <label class="text-xs font-semibold text-brand-600 uppercase tracking-widest">Descripción actual del catálogo</label>
                <p class="text-sm text-surface-500 mt-1">{{ producto.descripcion || '(sin descripción)' }}</p>
            </div>

            <div class="card p-4">
                <label class="text-xs font-semibold">Copy comercial (para redes/tienda)</label>
                <textarea v-model="form.copy_comercial" rows="4" class="input w-full" placeholder="Ideal para bebés de 3 a 12 meses..."></textarea>
            </div>

            <div class="card p-4">
                <label class="text-xs font-semibold">Beneficios (USPs, uno por línea)</label>
                <textarea v-model="form.beneficios" rows="4" class="input w-full" placeholder="• 100% algodón hipoalergénico&#10;• Cierre con broches de presión&#10;• Certificación OEKO-TEX"></textarea>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold">Especificaciones técnicas</label>
                    <button @click="addSpec" class="btn-ghost text-xs"><Plus class="h-3 w-3"/> Agregar</button>
                </div>
                <div v-for="(s, i) in form.specs" :key="i" class="flex items-center gap-2 mb-2">
                    <input v-model="s.k" placeholder="Ej: Material" class="input w-40"/>
                    <input v-model="s.v" placeholder="Ej: Algodón 100%" class="input flex-1"/>
                    <button v-if="form.specs.length > 1" @click="rmSpec(i)" class="text-red-600"><Trash2 class="h-4 w-4"/></button>
                </div>
            </div>

            <div class="card p-4">
                <label class="text-xs font-semibold">Keywords SEO (separadas por coma)</label>
                <input v-model="form.keywords_seo" class="input w-full" placeholder="ropa bebe, body algodon, ropa recien nacido"/>
            </div>
        </div>
    </AppLayout>
</template>
