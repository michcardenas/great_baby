<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ShieldCheck, Users, Plus, Lock, Trash2, KeyRound } from 'lucide-vue-next';

defineProps({
    roles: Array,
    total_permisos: Number,
});

const eliminar = (rol) => {
    if (! window.confirm(`¿Eliminar el rol «${rol.nombre}»?\n\nEsta acción no se puede deshacer.`)) return;
    router.delete(`/app/roles/${rol.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Roles y permisos"/>
    <AppLayout>
        <div class="space-y-5 max-w-5xl mx-auto">

            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <ShieldCheck class="h-6 w-6 text-brand-600"/>
                        Roles y permisos
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">
                        Creá un rol, marcá qué puede ver y elegí quién lo tiene.
                        Hay {{ total_permisos }} accesos para repartir.
                    </p>
                </div>
                <Link href="/app/roles/nuevo" class="btn-primary">
                    <Plus class="h-4 w-4"/> Nuevo rol
                </Link>
            </div>

            <div class="card overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-surface-500 bg-surface-50 dark:bg-surface-900 border-b">
                        <tr>
                            <th class="p-3 text-left">Rol</th>
                            <th class="p-3 text-center">Personas</th>
                            <th class="p-3 text-center">Accesos</th>
                            <th class="p-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                        <tr v-for="r in roles" :key="r.id" class="hover:bg-brand-50/40 dark:hover:bg-brand-950/20">
                            <td class="p-3">
                                <div class="font-semibold flex items-center gap-2">
                                    {{ r.nombre }}
                                    <span v-if="r.protegido"
                                          class="inline-flex items-center gap-1 text-[10px] font-bold uppercase bg-surface-200 dark:bg-surface-800 text-surface-600 dark:text-surface-300 px-2 py-0.5 rounded"
                                          title="Rol del sistema: no se puede eliminar ni renombrar">
                                        <Lock class="h-3 w-3"/> del sistema
                                    </span>
                                </div>
                                <div v-if="r.root" class="text-xs text-amber-700 mt-0.5">
                                    Ve todo el sistema sin importar las casillas.
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center gap-1 text-surface-600">
                                    <Users class="h-3.5 w-3.5"/> {{ r.usuarios }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <span v-if="r.root" class="text-xs font-semibold text-amber-700">todos</span>
                                <span v-else class="inline-flex items-center gap-1 text-surface-600">
                                    <KeyRound class="h-3.5 w-3.5"/> {{ r.permisos }}
                                </span>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <Link :href="`/app/roles/${r.id}`"
                                      class="inline-flex items-center px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded text-xs font-bold">
                                    Configurar
                                </Link>
                                <button v-if="!r.protegido" @click="eliminar(r)"
                                        class="ml-2 inline-flex items-center gap-1 px-2 py-1.5 text-red-600 hover:bg-red-50 rounded text-xs font-semibold">
                                    <Trash2 class="h-3.5 w-3.5"/>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card p-4 text-xs text-surface-500">
                <div class="font-semibold text-surface-700 dark:text-surface-300 mb-2">Cómo funciona</div>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Un rol agrupa accesos. Una persona puede tener más de un rol y suma los accesos de todos.</li>
                    <li>Los roles <b>del sistema</b> no se pueden eliminar ni renombrar porque el programa los nombra por dentro; sus accesos sí se pueden ajustar.</li>
                    <li><b>Aracely</b> y <b>Gerencia</b> ven todo siempre: si alguien se equivoca marcando casillas, tiene que quedar alguien que pueda entrar a corregirlo.</li>
                    <li>Para eliminar un rol primero hay que dejarlo sin personas asignadas.</li>
                </ul>
            </div>

        </div>
    </AppLayout>
</template>
