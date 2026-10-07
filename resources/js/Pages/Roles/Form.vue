<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, ShieldCheck, Lock, Users, Search, Save } from 'lucide-vue-next';

const props = defineProps({
    rol: Object,         // null = rol nuevo
    catalogo: Object,    // { grupo: [{seccion, permiso, etiqueta}] }
    usuarios: Array,
});

const esNuevo = ! props.rol;

const nombre = ref(props.rol?.nombre ?? '');
const permisos = ref([...(props.rol?.permisos ?? [])]);
const asignados = ref([...(props.rol?.usuarios ?? [])]);
const guardando = ref(false);
const buscar = ref('');

const esRoot = props.rol?.root ?? false;
const esProtegido = props.rol?.protegido ?? false;

const tiene = (p) => permisos.value.includes(p);

const alternar = (p) => {
    const i = permisos.value.indexOf(p);
    i === -1 ? permisos.value.push(p) : permisos.value.splice(i, 1);
};

const grupoCompleto = (items) => items.every(i => tiene(i.permiso));

const alternarGrupo = (items) => {
    if (grupoCompleto(items)) {
        permisos.value = permisos.value.filter(p => ! items.some(i => i.permiso === p));
    } else {
        items.forEach(i => { if (! tiene(i.permiso)) permisos.value.push(i.permiso); });
    }
};

const usuariosFiltrados = computed(() => {
    const q = buscar.value.trim().toLowerCase();
    if (! q) return props.usuarios;
    return props.usuarios.filter(u =>
        u.nombre.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
});

const alternarUsuario = (id) => {
    const i = asignados.value.indexOf(id);
    i === -1 ? asignados.value.push(id) : asignados.value.splice(i, 1);
};

const guardar = () => {
    guardando.value = true;
    router.post(esNuevo ? '/app/roles' : `/app/roles/${props.rol.id}`, {
        nombre: nombre.value,
        permisos: permisos.value,
        usuarios: asignados.value,
    }, { onFinish: () => { guardando.value = false; } });
};
</script>

<template>
    <Head :title="esNuevo ? 'Nuevo rol' : `Rol ${rol.nombre}`"/>
    <AppLayout>
        <div class="space-y-5 max-w-5xl mx-auto">

            <Link href="/app/roles" class="text-sm text-brand-600 hover:underline inline-flex items-center gap-1">
                <ArrowLeft class="h-4 w-4"/> Volver a roles
            </Link>

            <h1 class="text-2xl font-bold flex items-center gap-2">
                <ShieldCheck class="h-6 w-6 text-brand-600"/>
                {{ esNuevo ? 'Nuevo rol' : `Rol ${rol.nombre}` }}
            </h1>

            <!-- Nombre -->
            <div class="card p-4">
                <label class="block text-xs font-semibold uppercase text-surface-500 mb-1">Nombre del rol</label>
                <input v-model="nombre" :disabled="esProtegido" type="text" maxlength="60"
                       placeholder="Por ejemplo: Auxiliar contable"
                       class="input w-full max-w-sm"
                       :class="{ 'opacity-60 cursor-not-allowed': esProtegido }"/>
                <p v-if="esProtegido" class="text-xs text-surface-500 mt-2 flex items-center gap-1">
                    <Lock class="h-3 w-3"/>
                    Es un rol del sistema: el nombre no se puede cambiar porque el programa lo usa por dentro.
                    Sus accesos y sus personas sí se pueden ajustar.
                </p>
            </div>

            <!-- Aviso root -->
            <div v-if="esRoot" class="card p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-900 text-sm">
                <b>Este rol ve todo el sistema.</b> Las casillas quedan marcadas como referencia,
                pero su acceso no depende de ellas: siempre tiene que existir alguien que pueda
                entrar a corregir una configuración equivocada.
            </div>

            <!-- Permisos -->
            <div class="card overflow-hidden">
                <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-800">
                    <h3 class="font-semibold">¿Qué puede ver y hacer?</h3>
                    <p class="text-xs text-surface-500 mt-0.5">
                        {{ permisos.length }} accesos marcados.
                    </p>
                </div>

                <div class="divide-y divide-surface-100 dark:divide-surface-800">
                    <div v-for="(items, grupo) in catalogo" :key="grupo" class="p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-xs font-bold uppercase text-surface-500">{{ grupo }}</h4>
                            <button @click="alternarGrupo(items)" type="button"
                                    class="text-xs text-brand-600 hover:underline font-semibold">
                                {{ grupoCompleto(items) ? 'Quitar todo' : 'Marcar todo' }}
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <label v-for="i in items" :key="i.permiso"
                                   class="flex items-center gap-2 p-2 rounded border border-surface-200 dark:border-surface-700 cursor-pointer hover:bg-brand-50/50 dark:hover:bg-brand-950/20 text-sm">
                                <input type="checkbox" :checked="tiene(i.permiso)" @change="alternar(i.permiso)"
                                       class="h-4 w-4 rounded shrink-0"/>
                                <span>{{ i.etiqueta }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Usuarios -->
            <div class="card overflow-hidden">
                <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-800 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h3 class="font-semibold flex items-center gap-2">
                            <Users class="h-5 w-5 text-brand-600"/>
                            ¿Quiénes tienen este rol?
                        </h3>
                        <p class="text-xs text-surface-500 mt-0.5">{{ asignados.length }} persona(s) seleccionada(s).</p>
                    </div>
                    <div class="relative">
                        <Search class="h-4 w-4 absolute left-2 top-2.5 text-surface-400"/>
                        <input v-model="buscar" type="search" placeholder="Buscar persona…" class="input pl-8 w-56"/>
                    </div>
                </div>
                <div class="max-h-80 overflow-y-auto divide-y divide-surface-100 dark:divide-surface-800">
                    <label v-for="u in usuariosFiltrados" :key="u.id"
                           class="flex items-center gap-3 p-3 cursor-pointer hover:bg-brand-50/40 dark:hover:bg-brand-950/20">
                        <input type="checkbox" :checked="asignados.includes(u.id)" @change="alternarUsuario(u.id)"
                               class="h-4 w-4 rounded shrink-0"/>
                        <div class="min-w-0">
                            <div class="font-medium truncate">{{ u.nombre }}</div>
                            <div class="text-xs text-surface-500 truncate">{{ u.email }}</div>
                        </div>
                    </label>
                    <div v-if="!usuariosFiltrados.length" class="p-6 text-center text-sm text-surface-400">
                        Nadie coincide con la búsqueda.
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <Link href="/app/roles" class="text-sm text-surface-500 hover:underline">Cancelar</Link>
                <button @click="guardar" :disabled="guardando || !nombre.trim()"
                        class="btn-primary"
                        :class="{ 'opacity-50 cursor-not-allowed': guardando || !nombre.trim() }">
                    <Save class="h-4 w-4"/>
                    {{ guardando ? 'Guardando…' : 'Guardar rol' }}
                </button>
            </div>

        </div>
    </AppLayout>
</template>
