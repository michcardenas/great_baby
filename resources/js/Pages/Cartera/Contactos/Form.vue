<script setup>
/*
 * Alta y edición de contactos.
 *
 * Esta pantalla sólo existía en el panel Filament. Al dejar `/admin` para
 * Dropi, el ERP se quedaba sin forma de dar de alta un cliente o un proveedor.
 */
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Save, User, AlertTriangle } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    contacto: { type: Object, default: null },
    listas: { type: Array, default: () => [] },
});

const editando = computed(() => !! props.contacto?.id);

const form = ref({
    tipo_documento: props.contacto?.tipo_documento ?? 'CC',
    numero_documento: props.contacto?.numero_documento ?? '',
    nombre_completo: props.contacto?.nombre_completo ?? '',
    razon_social: props.contacto?.razon_social ?? '',
    email: props.contacto?.email ?? '',
    telefono: props.contacto?.telefono ?? '',
    direccion: props.contacto?.direccion ?? '',
    ciudad: props.contacto?.ciudad ?? '',
    departamento: props.contacto?.departamento ?? '',
    es_cliente: props.contacto?.es_cliente ?? true,
    es_cliente_b2b: props.contacto?.es_cliente_b2b ?? false,
    es_proveedor: props.contacto?.es_proveedor ?? false,
    es_empleado: props.contacto?.es_empleado ?? false,
    regimen_iva: props.contacto?.regimen_iva ?? '',
    lista_precios_id: props.contacto?.lista_precios_id ?? '',
    activo: props.contacto?.activo ?? true,
});

const guardando = ref(false);

// Un B2B sin lista no se puede facturar ni aparece en el armador del vendedor.
const faltaLista = computed(() => form.value.es_cliente_b2b && ! form.value.lista_precios_id);

const puedeGuardar = computed(() =>
    form.value.numero_documento.trim() !== ''
    && form.value.nombre_completo.trim() !== ''
    && ! faltaLista.value
    && ! guardando.value);

const guardar = () => {
    if (! puedeGuardar.value) return;
    guardando.value = true;
    const url = editando.value ? `/app/contactos/${props.contacto.id}` : '/app/contactos';
    router.post(url, {
        ...form.value,
        lista_precios_id: form.value.lista_precios_id === '' ? null : Number(form.value.lista_precios_id),
        regimen_iva: form.value.regimen_iva === '' ? null : form.value.regimen_iva,
    }, { onFinish: () => (guardando.value = false) });
};
</script>

<template>
    <Head :title="editando ? 'Editar contacto' : 'Nuevo contacto'"/>
    <AppLayout>
        <div class="max-w-3xl mx-auto space-y-4">
            <Link :href="editando ? `/app/contactos/${contacto.id}` : '/app/contactos'"
                  class="btn-ghost inline-flex items-center gap-1 text-sm min-h-11">
                <ArrowLeft class="h-4 w-4"/> Volver
            </Link>

            <h1 class="text-2xl font-bold flex items-center gap-2">
                <User class="h-6 w-6 text-brand-600"/>
                {{ editando ? 'Editar contacto' : 'Nuevo contacto' }}
            </h1>

            <div v-if="$page.props.errors && Object.keys($page.props.errors).length"
                 class="p-3 rounded-lg bg-red-50 dark:bg-red-950/40 border-l-4 border-red-500 text-red-700 dark:text-red-300 text-sm space-y-1">
                <div v-for="(msg, k) in $page.props.errors" :key="k">{{ msg }}</div>
            </div>

            <div class="card p-4 space-y-3">
                <div class="text-xs uppercase font-bold text-brand-600">Identificación</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Tipo de documento</label>
                        <select v-model="form.tipo_documento" class="input w-full min-h-11">
                            <option value="CC">Cédula</option>
                            <option value="CE">Cédula extranjera</option>
                            <option value="NIT">NIT empresa</option>
                            <option value="PP">Pasaporte</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Número *</label>
                        <input v-model="form.numero_documento" class="input w-full min-h-11" maxlength="30"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Nombre completo *</label>
                        <input v-model="form.nombre_completo" class="input w-full min-h-11" maxlength="180"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Razón social</label>
                        <input v-model="form.razon_social" class="input w-full min-h-11" maxlength="180"
                               placeholder="Si es empresa"/>
                    </div>
                </div>
            </div>

            <div class="card p-4 space-y-3">
                <div class="text-xs uppercase font-bold text-brand-600">Contacto</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Correo</label>
                        <input v-model="form.email" type="email" class="input w-full min-h-11" maxlength="120"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Teléfono</label>
                        <input v-model="form.telefono" type="tel" class="input w-full min-h-11" maxlength="30"/>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Dirección</label>
                        <input v-model="form.direccion" class="input w-full min-h-11" maxlength="200"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Ciudad</label>
                        <input v-model="form.ciudad" class="input w-full min-h-11" maxlength="80"/>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Departamento</label>
                        <input v-model="form.departamento" class="input w-full min-h-11" maxlength="80"/>
                    </div>
                </div>
            </div>

            <div class="card p-4 space-y-3">
                <div class="text-xs uppercase font-bold text-brand-600">¿Qué es para la empresa?</div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <label class="flex items-center gap-2 p-2 rounded min-h-11 cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800">
                        <input type="checkbox" v-model="form.es_cliente" class="h-4 w-4"/> <span class="text-sm">Cliente</span>
                    </label>
                    <label class="flex items-center gap-2 p-2 rounded min-h-11 cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800">
                        <input type="checkbox" v-model="form.es_cliente_b2b" class="h-4 w-4"/> <span class="text-sm">Cliente B2B (con crédito)</span>
                    </label>
                    <label class="flex items-center gap-2 p-2 rounded min-h-11 cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800">
                        <input type="checkbox" v-model="form.es_proveedor" class="h-4 w-4"/> <span class="text-sm">Proveedor</span>
                    </label>
                    <label class="flex items-center gap-2 p-2 rounded min-h-11 cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800">
                        <input type="checkbox" v-model="form.es_empleado" class="h-4 w-4"/> <span class="text-sm">Empleado</span>
                    </label>
                    <label class="flex items-center gap-2 p-2 rounded min-h-11 cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800">
                        <input type="checkbox" v-model="form.activo" class="h-4 w-4"/> <span class="text-sm">Activo</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Lista de precios</label>
                        <select v-model="form.lista_precios_id" class="input w-full min-h-11"
                                :class="faltaLista && 'border-amber-500'">
                            <option value="">— Sin lista —</option>
                            <option v-for="l in listas" :key="l.id" :value="l.id">{{ l.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-surface-600 dark:text-surface-300">Régimen de IVA</label>
                        <select v-model="form.regimen_iva" class="input w-full min-h-11">
                            <option value="">— Sin definir —</option>
                            <option value="responsable">Responsable de IVA</option>
                            <option value="no_responsable">No responsable</option>
                        </select>
                    </div>
                </div>

                <div v-if="faltaLista"
                     class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border-l-4 border-amber-500 text-amber-800 dark:text-amber-200 text-xs flex items-start gap-2">
                    <AlertTriangle class="h-4 w-4 flex-shrink-0 mt-0.5"/>
                    <div>Un cliente B2B necesita lista de precios: sin ella no aparece cuando el vendedor va a armarle un pedido.</div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Link :href="editando ? `/app/contactos/${contacto.id}` : '/app/contactos'" class="btn-ghost min-h-11">Cancelar</Link>
                <button @click="guardar" :disabled="! puedeGuardar" class="btn-primary min-h-11 disabled:opacity-40">
                    <Save class="h-4 w-4"/> {{ guardando ? 'Guardando…' : (editando ? 'Guardar cambios' : 'Crear contacto') }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
