<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Search, UserPlus } from 'lucide-vue-next';

defineProps({
    clientes: { type: Array, required: true },
    q: { type: String, default: null },
});

const query = ref('');
const buscar = () => router.get('/app/vendedor', { q: query.value || null }, { preserveState: true });
</script>

<template>
    <Head title="Nuevo pedido · vendedor"/>
    <AppLayout>
        <div class="space-y-4 max-w-4xl mx-auto">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    🛒 Nuevo pedido a nombre de cliente
                </h1>
                <p class="text-sm text-surface-500 mt-1">
                    Elegí al cliente que estás visitando para armar su pedido con los precios que le corresponden.
                </p>
            </div>

            <!-- Buscador -->
            <div class="card p-4">
                <form @submit.prevent="buscar" class="flex gap-2">
                    <div class="flex-1 relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400"/>
                        <input v-model="query" type="text" placeholder="Razón social, NIT, correo o nombre…"
                               class="w-full pl-10 pr-4 py-2.5 border border-surface-300 rounded-lg dark:bg-surface-900 focus:border-brand-500 focus:outline-none"
                               autofocus>
                    </div>
                    <button type="submit" class="btn-primary">Buscar</button>
                </form>
            </div>

            <!-- Resultados -->
            <div v-if="!clientes.length" class="card p-10 text-center text-surface-500">
                <UserPlus class="h-10 w-10 mx-auto mb-3 opacity-40"/>
                <div v-if="q">Sin coincidencias con «{{ q }}».</div>
                <div v-else>Escribí al menos parte del nombre o NIT y presioná Buscar.</div>
            </div>
            <div v-else class="card divide-y divide-surface-200 dark:divide-surface-800">
                <Link v-for="c in clientes" :key="c.id"
                      :href="`/app/vendedor/pedido-nuevo/${c.id}`"
                      class="block p-3 hover:bg-brand-50 dark:hover:bg-surface-800 transition">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-bold">{{ c.razon_social || c.nombre_completo }}</div>
                            <div class="text-xs text-surface-500">
                                NIT {{ c.numero_documento }} · {{ c.ciudad || 'sin ciudad' }}{{ c.telefono ? ' · ' + c.telefono : '' }}
                            </div>
                        </div>
                        <div class="btn-primary text-xs">Armar pedido →</div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
