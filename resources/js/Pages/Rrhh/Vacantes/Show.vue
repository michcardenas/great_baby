<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Briefcase, Plus, Star } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ vacante: { type: Object, required: true }, candidatos: { type: Array, required: true } });

const modal = ref(false);
const form = reactive({ nombre: '', email: '', telefono: '', notas: '' });
const procesando = ref(false);
const crear = () => {
    if (procesando.value) return;
    procesando.value = true;
    router.post(`/app/rrhh/vacantes/${props.vacante.id}/candidatos`, form, {
        preserveScroll: true,
        onSuccess: () => { modal.value = false; Object.assign(form, { nombre: '', email: '', telefono: '', notas: '' }); },
        onFinish: () => procesando.value = false,
    });
};

const actualizar = (c, campo, valor) => {
    router.put(`/app/rrhh/candidatos/${c.id}`, { etapa: campo === 'etapa' ? valor : c.etapa, calificacion: campo === 'calificacion' ? valor : c.calificacion, notas: c.notas }, { preserveScroll: true });
};

const etapas = ['nuevo', 'revision', 'entrevista', 'prueba', 'oferta', 'contratado', 'descartado'];
const etapaBadge = (e) => ({
    nuevo: 'bg-surface-100', revision: 'bg-blue-100 text-blue-800', entrevista: 'bg-amber-100 text-amber-800',
    prueba: 'bg-purple-100 text-purple-800', oferta: 'bg-brand-100 text-brand-800',
    contratado: 'bg-emerald-100 text-emerald-800', descartado: 'bg-red-100 text-red-800',
}[e] || 'bg-surface-100');
</script>

<template>
    <Head :title="vacante.titulo"/>
    <AppLayout>
        <div class="max-w-4xl mx-auto space-y-4">
            <Link href="/app/rrhh/vacantes" class="btn-ghost inline-flex items-center gap-1 text-sm"><ArrowLeft class="h-4 w-4"/> Volver</Link>

            <div v-if="$page.props.flash?.success" class="p-3 rounded-lg bg-emerald-500/15 border-l-4 border-emerald-500 text-emerald-700 text-sm">{{ $page.props.flash.success }}</div>

            <div class="card p-5">
                <h1 class="text-2xl font-bold flex items-center gap-2"><Briefcase class="h-6 w-6 text-brand-600"/>{{ vacante.titulo }}</h1>
                <p class="text-sm text-surface-500 mt-1">{{ vacante.area }} · {{ vacante.modalidad }} · {{ vacante.tipo_contrato }}</p>
                <div v-if="vacante.descripcion" class="mt-3 text-sm whitespace-pre-wrap">{{ vacante.descripcion }}</div>
                <div v-if="vacante.requisitos" class="mt-3">
                    <div class="text-xs uppercase font-bold text-brand-600 mb-1">Requisitos</div>
                    <p class="text-sm whitespace-pre-wrap">{{ vacante.requisitos }}</p>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs uppercase font-bold text-brand-600">Candidatos ({{ candidatos.length }})</div>
                    <button @click="modal = true" class="btn-primary text-sm"><Plus class="h-4 w-4"/> Agregar candidato</button>
                </div>
                <div v-if="!candidatos.length" class="text-center py-6 text-surface-500 text-sm">Sin candidatos.</div>
                <table v-else class="w-full text-sm">
                    <thead class="text-[10px] text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-2">Nombre</th>
                            <th class="text-left p-2">Contacto</th>
                            <th class="text-center p-2">Calif</th>
                            <th class="text-left p-2">Etapa</th>
                            <th class="text-left p-2">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="c in candidatos" :key="c.id">
                            <td class="p-2 font-medium">{{ c.nombre }}</td>
                            <td class="p-2 text-xs">
                                <div>{{ c.email }}</div>
                                <div>{{ c.telefono }}</div>
                            </td>
                            <td class="p-2 text-center">
                                <select :value="c.calificacion" @change="e => actualizar(c, 'calificacion', parseInt(e.target.value) || null)" class="input text-xs w-14">
                                    <option :value="null">—</option>
                                    <option v-for="n in 5" :key="n" :value="n">{{ n }}★</option>
                                </select>
                            </td>
                            <td class="p-2">
                                <select :value="c.etapa" @change="e => actualizar(c, 'etapa', e.target.value)"
                                    :class="['input text-xs', etapaBadge(c.etapa)]">
                                    <option v-for="e in etapas" :key="e" :value="e">{{ e }}</option>
                                </select>
                            </td>
                            <td class="p-2 text-xs">{{ c.fecha }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="modal" @click.self="modal = false" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="card p-6 max-w-md w-full">
                <h3 class="text-lg font-bold mb-3">Agregar candidato</h3>
                <div class="space-y-3">
                    <div><label class="text-xs font-semibold">Nombre</label><input v-model="form.nombre" class="input w-full" autofocus/></div>
                    <div><label class="text-xs font-semibold">Email</label><input v-model="form.email" type="email" class="input w-full"/></div>
                    <div><label class="text-xs font-semibold">Teléfono</label><input v-model="form.telefono" class="input w-full"/></div>
                    <div><label class="text-xs font-semibold">Notas iniciales</label><textarea v-model="form.notas" rows="2" class="input w-full"></textarea></div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button @click="modal = false" class="btn-ghost">Cancelar</button>
                    <button @click="crear" :disabled="procesando" class="btn-primary">{{ procesando ? '…' : 'Agregar' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
