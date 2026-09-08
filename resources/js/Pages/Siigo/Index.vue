<script setup>
import { Head } from '@inertiajs/vue3';
import { Cloud, CheckCircle, XCircle, ExternalLink } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    config: { type: Object, default: null },
    logs: { type: Array, required: true },
});
</script>

<template>
    <Head title="SIIGO"/>
    <AppLayout>
        <div class="space-y-4 max-w-5xl">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <Cloud class="h-6 w-6 text-brand-600"/>
                    Integración SIIGO
                </h1>
                <p class="text-sm text-surface-500 mt-1">Estado de conexión y bitácora de sincronizaciones (productos, clientes, facturas).</p>
            </div>

            <!-- Estado -->
            <div class="card p-5">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">Estado de conexión</div>
                <div v-if="!config" class="text-sm text-amber-600">
                    ⚠ SIIGO aún no configurado. Aracely: cuando tengas las credenciales, configurá desde el
                    <a href="/admin" class="text-brand-600 hover:underline flex items-center gap-1 inline-flex">panel admin <ExternalLink class="h-3 w-3"/></a>.
                </div>
                <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <div class="text-xs text-surface-500">Ambiente</div>
                        <div class="font-semibold capitalize">{{ config.ambiente }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-surface-500">Usuario</div>
                        <div class="font-mono text-xs">{{ config.usuario }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-surface-500">Conectado</div>
                        <div class="font-mono text-xs">{{ config.conectado_at || 'No conectado' }}</div>
                    </div>
                </div>
            </div>

            <!-- Bitácora -->
            <div class="card p-5">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">Últimas sincronizaciones ({{ logs.length }})</div>
                <div v-if="!logs.length" class="text-center py-10 text-surface-500 text-sm">Sin sincronizaciones registradas aún.</div>
                <div v-else class="space-y-2">
                    <div v-for="l in logs" :key="l.id" class="flex items-center gap-3 p-3 rounded-lg border border-surface-200 dark:border-surface-800">
                        <CheckCircle v-if="l.exitoso" class="h-5 w-5 text-emerald-600 flex-shrink-0"/>
                        <XCircle v-else class="h-5 w-5 text-red-600 flex-shrink-0"/>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm">{{ l.tipo }}</div>
                            <div class="text-xs text-surface-500">
                                {{ l.items_procesados }} procesados
                                <span v-if="l.items_error > 0" class="text-red-600">· {{ l.items_error }} errores</span>
                            </div>
                            <div v-if="l.mensaje" class="text-xs text-surface-500 mt-1">{{ l.mensaje }}</div>
                        </div>
                        <div class="text-xs text-surface-500">{{ l.hace }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
