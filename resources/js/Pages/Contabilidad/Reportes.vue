<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, FileText, Clock, Cloud, CloudOff, Download } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    reportes: { type: Array, required: true },
    siigo_estado: { type: Object, default: null },
});

// Agrupar por familia.
const familias = computed(() => {
    const g = {};
    props.reportes.forEach((r) => {
        const fam = r.familia || 'Otros';
        (g[fam] ??= []).push(r);
    });
    return g;
});

const totalListos = computed(() => props.reportes.filter(r => r.listo).length);
</script>

<template>
    <Head title="Reportes contables · SIIGO"/>
    <AppLayout>
        <div class="space-y-6 max-w-5xl">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <BarChart3 class="h-6 w-6 text-brand-600"/>
                    Reportes contables
                </h1>
                <p class="text-sm text-surface-500 mt-1">
                    {{ totalListos }} de {{ reportes.length }} reportes listos · todos con cuentas PUC Great Baby.
                </p>
            </div>

            <!-- Sprint 3 · F.4 · estado SIIGO global visible en el hub -->
            <div v-if="siigo_estado" class="card p-4 flex items-center justify-between gap-4 flex-wrap"
                 :class="siigo_estado.push_activo ? 'border-l-4 border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-l-4 border-amber-500 bg-amber-50/40 dark:bg-amber-950/20'">
                <div class="flex items-center gap-3">
                    <Cloud v-if="siigo_estado.push_activo" class="h-6 w-6 text-emerald-600"/>
                    <CloudOff v-else class="h-6 w-6 text-amber-600"/>
                    <div>
                        <div class="font-bold" :class="siigo_estado.push_activo ? 'text-emerald-700' : 'text-amber-700'">
                            {{ siigo_estado.push_activo ? 'Sync SIIGO activo' : 'Sync SIIGO pausado' }}
                            <span class="text-xs uppercase font-normal ml-2 px-2 py-0.5 rounded bg-surface-200 dark:bg-surface-800">{{ siigo_estado.ambiente }}</span>
                        </div>
                        <div class="text-xs text-surface-500">
                            Última sync productos: {{ siigo_estado.ultima_sync_productos || 'nunca' }}
                        </div>
                    </div>
                </div>
                <Link href="/app/siigo" class="btn-ghost text-sm">Panel SIIGO →</Link>
            </div>

            <div v-for="(items, fam) in familias" :key="fam" class="space-y-2">
                <h2 class="text-xs uppercase tracking-widest font-bold text-surface-500">{{ fam }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <template v-for="(r) in items" :key="(r.href || r.nombre)">
                        <Link v-if="r.listo && r.href" :href="r.href"
                            class="card p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all relative">
                            <FileText class="h-8 w-8 text-brand-600 mb-2"/>
                            <div class="font-bold">{{ r.nombre }}</div>
                            <p class="text-xs text-surface-500 mt-1">{{ r.desc }}</p>
                            <!-- F.4 · badge de export y compat SIIGO -->
                            <div class="mt-3 flex items-center gap-2 flex-wrap">
                                <span v-if="r.export" class="inline-flex items-center gap-1 text-[10px] font-semibold bg-blue-100 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 px-2 py-0.5 rounded">
                                    <Download class="h-3 w-3"/> {{ r.export }}
                                </span>
                                <span v-if="r.siigo_ok" class="inline-flex items-center gap-1 text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 rounded">
                                    <Cloud class="h-3 w-3"/> SIIGO
                                </span>
                            </div>
                        </Link>
                        <div v-else class="card p-5 opacity-50 cursor-not-allowed"
                             role="button" aria-disabled="true"
                             title="Aún en desarrollo — no depende de permisos.">
                            <Clock class="h-8 w-8 text-surface-400 mb-2"/>
                            <div class="font-bold text-surface-500">{{ r.nombre }}</div>
                            <p class="text-xs text-surface-500 mt-1">{{ r.desc }}</p>
                            <span class="mt-2 inline-block text-[10px] font-bold uppercase bg-surface-200 dark:bg-surface-800 text-surface-600 dark:text-surface-400 px-2 py-0.5 rounded">Próximamente</span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Info footer sobre formato SIIGO -->
            <div class="card p-4 text-xs text-surface-500">
                <div class="font-semibold text-surface-700 dark:text-surface-300 mb-2">Compatibilidad SIIGO</div>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Todos los reportes marcados con <Cloud class="h-3 w-3 inline text-emerald-600"/> usan cuentas del PUC oficial de Great Baby (1073 cuentas cargadas).</li>
                    <li>Los movimientos contables se generan desde el ERP (Cartera, Compras, Inventario) y se replican a SIIGO en tiempo real cuando el push está activo.</li>
                    <li>Diferencias entre estos reportes locales y SIIGO indican asientos pendientes de sincronización — ver <Link href="/app/siigo" class="text-brand-600 underline">panel SIIGO</Link>.</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
