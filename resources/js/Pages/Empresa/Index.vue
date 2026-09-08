<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Building2, Save } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    empresa: { type: Object, required: true },
});

// QA-D Bloque2: useForm en vez de reactive+router.post. Así:
// - form.errors se pinta automático por campo
// - form.processing bloquea el botón (evita doble-click)
// - preserveScroll y onSuccess resetean solo si guardó bien
const form = useForm({ ...props.empresa });

const guardar = () => {
    if (form.processing) return;
    form.post('/app/empresa', {
        preserveScroll: true,
        onError: () => {
            if (typeof window !== 'undefined') {
                window.dispatchEvent(new CustomEvent('gb:error', { detail: { mensaje: 'Revisa los campos marcados en rojo.' } }));
            }
        },
    });
};

// Grupos de campos
const grupos = [
    {
        titulo: 'Datos legales', campos: [
            { k: 'razon_social', l: 'Razón social', req: true },
            { k: 'nombre_comercial', l: 'Nombre comercial' },
            { k: 'nit', l: 'NIT', req: true },
            { k: 'regimen', l: 'Régimen IVA' },
            { k: 'actividad_economica', l: 'CIIU / Actividad económica' },
        ]
    },
    {
        titulo: 'Ubicación y contacto', campos: [
            { k: 'direccion', l: 'Dirección' },
            { k: 'ciudad', l: 'Ciudad' },
            { k: 'departamento', l: 'Departamento' },
            { k: 'pais', l: 'País' },
            { k: 'telefono', l: 'Teléfono' },
            { k: 'email', l: 'Email', type: 'email' },
            { k: 'web', l: 'Sitio web' },
        ]
    },
    {
        titulo: 'Resolución DIAN (facturación electrónica)', campos: [
            { k: 'resolucion_dian', l: 'Número de resolución' },
            { k: 'prefijo_dian', l: 'Prefijo' },
            { k: 'resolucion_desde', l: 'Vigencia desde', type: 'date' },
            { k: 'resolucion_hasta', l: 'Vigencia hasta', type: 'date' },
            { k: 'rango_desde', l: 'Rango desde', type: 'number' },
            { k: 'rango_hasta', l: 'Rango hasta', type: 'number' },
        ]
    },
    {
        titulo: 'Datos bancarios', campos: [
            { k: 'banco_nombre', l: 'Banco' },
            { k: 'banco_cuenta', l: 'Número de cuenta' },
            { k: 'banco_moneda', l: 'Moneda' },
            { k: 'banco_swift', l: 'SWIFT' },
            { k: 'banco_iban', l: 'IBAN' },
        ]
    },
    {
        titulo: 'Contacto financiero', campos: [
            { k: 'financiero_nombre', l: 'Nombre' },
            { k: 'financiero_email', l: 'Email', type: 'email' },
            { k: 'financiero_telefono', l: 'Teléfono' },
        ]
    },
];
</script>

<template>
    <Head title="Empresa"/>
    <AppLayout>
        <div class="space-y-4 max-w-5xl">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Building2 class="h-6 w-6 text-brand-600"/>
                        Configuración de empresa
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">Estos datos aparecen en facturas, PDF y como emisor DIAN.</p>
                </div>
                <button @click="guardar" :disabled="form.processing" class="btn-primary disabled:opacity-50">
                    <Save class="h-4 w-4"/> {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                </button>
            </div>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ $page.props.flash.success }}
            </div>

            <div v-for="g in grupos" :key="g.titulo" class="card p-5">
                <h2 class="text-sm font-bold text-brand-600 uppercase tracking-wider mb-4">{{ g.titulo }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div v-for="c in g.campos" :key="c.k">
                        <label :for="'f-' + c.k" class="block text-xs font-semibold text-surface-600 dark:text-surface-400 mb-1">
                            {{ c.l }}<span v-if="c.req" class="text-red-500">*</span>
                        </label>
                        <input :id="'f-' + c.k" v-model="form[c.k]" :type="c.type || 'text'"
                               :class="['input w-full', form.errors[c.k] ? 'border-red-500 ring-red-500' : '']"
                               :placeholder="c.l"/>
                        <p v-if="form.errors[c.k]" class="text-xs text-red-600 mt-1">{{ form.errors[c.k] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
