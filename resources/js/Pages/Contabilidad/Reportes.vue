<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, FileText, Clock } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ reportes: { type: Array, required: true } });

// Re-audit UX-M8 · agrupar por familia. Aracely busca "Libro diario" y hoy
// tiene que leer las 9 tarjetas. Con familia (Estados, Auxiliares, Impuestos)
// escanea en segundos.
const familias = computed(() => {
    const g = {};
    props.reportes.forEach((r) => {
        const fam = r.familia || 'Otros';
        (g[fam] ??= []).push(r);
    });
    return g;
});
</script>

<template>
    <Head title="Reportes contables"/>
    <AppLayout>
        <div class="space-y-6 max-w-5xl">
            <h1 class="text-2xl font-bold flex items-center gap-2"><BarChart3 class="h-6 w-6 text-brand-600"/>Reportes contables</h1>

            <div v-for="(items, fam) in familias" :key="fam" class="space-y-2">
                <h2 class="text-xs uppercase tracking-widest font-bold text-surface-500">{{ fam }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <!-- Re-audit R2 UX-M2 · key estable por href/nombre para evitar rearmes. -->
                    <template v-for="(r) in items" :key="(r.href || r.nombre)">
                        <!-- Re-audit UX-C1 · reportes `listo=false` como card gris sin link. -->
                        <Link v-if="r.listo && r.href" :href="r.href"
                            class="card p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                            <FileText class="h-8 w-8 text-brand-600 mb-2"/>
                            <div class="font-bold">{{ r.nombre }}</div>
                            <p class="text-xs text-surface-500 mt-1">{{ r.desc }}</p>
                        </Link>
                        <!-- Re-audit R2 UX-A6 · Clock en vez de Lock; el candado se leía
                             como "sin permiso". Ahora reloj + tooltip explícito. -->
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
        </div>
    </AppLayout>
</template>
