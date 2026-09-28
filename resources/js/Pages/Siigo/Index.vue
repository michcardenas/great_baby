<script setup>
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import {
    Cloud, CheckCircle, XCircle, AlertCircle, Clock, RefreshCw,
    Power, PowerOff, ExternalLink, Package, ListChecks, Ban,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    config: { type: Object, required: true },
    kpis: { type: Object, required: true },
    cola: { type: Object, required: true },
    fallidos_recientes: { type: Array, required: true },
    logs: { type: Array, required: true },
    puede_toggle: { type: Boolean, default: false },
});

// F8 · toggle kill-switch (solo Aracely).
const togglando = ref(false);
const togglear = () => {
    if (! props.puede_toggle) return;
    const nuevo = ! props.config.push_auto;
    const msg = nuevo
        ? '¿Encender el sync automático a SIIGO?\n\nCada cambio de producto se enviará solo.'
        : '¿Apagar el sync automático?\n\nLos productos que edites NO se enviarán hasta que lo reactives (los jobs YA encolados también se pausan).';
    if (! confirm(msg)) return;
    togglando.value = true;
    router.post('/app/siigo/kill-switch', { activo: nuevo }, {
        preserveScroll: true,
        onFinish: () => { togglando.value = false; },
    });
};

// F8 · reintentar un fallido.
const reintentando = ref(null);
const reintentar = (logId) => {
    if (! confirm('¿Reintentar este sync? Se encolará un nuevo job manual.')) return;
    reintentando.value = logId;
    router.post(`/app/siigo/logs/${logId}/reintentar`, {}, {
        preserveScroll: true,
        onFinish: () => { reintentando.value = null; },
    });
};

// Semáforo global · rojo si hay fallidos hoy o kill-switch off, amarillo si sin sync reciente, verde si todo OK.
const semaforo = computed(() => {
    if (! props.config.activo || ! props.config.usuario) {
        return { color: 'gray', txt: 'Sin configurar' };
    }
    if (! props.config.push_auto) return { color: 'amber', txt: 'Push automático PAUSADO' };
    if (props.kpis.productos_hoy_fallidos > 0) return { color: 'red', txt: `${props.kpis.productos_hoy_fallidos} sync con error hoy` };
    return { color: 'emerald', txt: 'Todo funcionando' };
});

const claseSemaforo = computed(() => ({
    'text-emerald-600 bg-emerald-50 border-emerald-200': semaforo.value.color === 'emerald',
    'text-amber-600 bg-amber-50 border-amber-200': semaforo.value.color === 'amber',
    'text-red-600 bg-red-50 border-red-200': semaforo.value.color === 'red',
    'text-surface-500 bg-surface-50 border-surface-200': semaforo.value.color === 'gray',
}));

const claseEstadoLog = (l) => {
    if (l.estado === 'exitoso' || l.estado === 'reconciliado') return 'text-emerald-600';
    if (l.estado === 'fallido') return 'text-red-600';
    if (l.estado === 'omitido' || l.estado === 'ignorado') return 'text-surface-500';
    return 'text-amber-600';
};
</script>

<template>
    <Head title="SIIGO · Panel de sync"/>
    <AppLayout>
        <div class="space-y-4 max-w-6xl">

            <!-- Header con semáforo global -->
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Cloud class="h-6 w-6 text-brand-600"/>
                        Sincronización SIIGO
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">
                        Estado del cableado ERP ↔ SIIGO · productos, facturas, pagos, asientos.
                    </p>
                </div>
                <div :class="['px-4 py-2 border rounded-lg font-semibold text-sm', claseSemaforo]">
                    ● {{ semaforo.txt }}
                </div>
            </div>

            <!-- Kill-switch prominente -->
            <div class="card p-5" v-if="config.activo">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div :class="config.push_auto ? 'text-emerald-600' : 'text-amber-600'">
                            <Power v-if="config.push_auto" class="h-10 w-10"/>
                            <PowerOff v-else class="h-10 w-10"/>
                        </div>
                        <div>
                            <div class="font-bold text-lg">
                                Push automático · {{ config.push_auto ? 'ENCENDIDO' : 'APAGADO' }}
                            </div>
                            <p class="text-xs text-surface-500 mt-1 max-w-md">
                                <span v-if="config.push_auto">
                                    Cada cambio de producto (crear/editar/desactivar) se envía a SIIGO en menos de 30 s.
                                    El pull sigue corriendo cada 15 min sin importar este switch.
                                </span>
                                <span v-else>
                                    Los cambios se guardan en el ERP pero NO se envían a SIIGO. Los jobs ya encolados tampoco se ejecutan.
                                    El pull sigue corriendo.
                                </span>
                            </p>
                            <p v-if="config.push_auto_updated_at" class="text-[10px] text-surface-400 mt-1">
                                Último cambio: {{ config.push_auto_updated_at }} · fuente: {{ config.push_auto_source === 'ui' ? 'desde este panel' : '.env' }}
                            </p>
                        </div>
                    </div>
                    <button v-if="puede_toggle" @click="togglear" :disabled="togglando"
                            :class="['px-6 py-3 rounded-lg font-bold text-sm transition',
                                     config.push_auto
                                       ? 'bg-amber-600 hover:bg-amber-700 text-white'
                                       : 'bg-emerald-600 hover:bg-emerald-700 text-white',
                                     togglando ? 'opacity-50 cursor-wait' : '']">
                        {{ config.push_auto ? '⏸ Apagar' : '▶ Encender' }}
                    </button>
                    <div v-else class="text-xs text-surface-400 italic">Solo Aracely puede alternar</div>
                </div>
            </div>

            <!-- Warning: SIIGO aún no configurado -->
            <div v-if="!config.activo || !config.usuario" class="card p-5 bg-amber-50 dark:bg-amber-900/20 border-amber-200">
                <div class="flex items-start gap-3">
                    <AlertCircle class="h-6 w-6 text-amber-600 flex-shrink-0 mt-0.5"/>
                    <div>
                        <div class="font-bold text-amber-900 dark:text-amber-100">SIIGO aún no configurado</div>
                        <p class="text-sm text-amber-800 dark:text-amber-200 mt-1">
                            Cuando Aracely tenga las credenciales (usuario + access-key + partner-id), las carga desde el
                            <a href="/admin/integracion-siigo" class="underline font-semibold inline-flex items-center gap-1">
                                panel admin <ExternalLink class="h-3 w-3"/>
                            </a>.
                            El código está listo; solo falta encender la conexión.
                        </p>
                    </div>
                </div>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-1">
                        <CheckCircle class="h-3 w-3"/>Sincronizados HOY
                    </div>
                    <div class="text-2xl font-bold mt-1 text-emerald-600">{{ kpis.productos_hoy_exitosos }}</div>
                </div>
                <div class="card p-4" :class="kpis.productos_hoy_fallidos > 0 ? 'ring-2 ring-red-500' : ''">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-1">
                        <XCircle class="h-3 w-3"/>Errores HOY
                    </div>
                    <div class="text-2xl font-bold mt-1" :class="kpis.productos_hoy_fallidos > 0 ? 'text-red-600' : 'text-surface-400'">
                        {{ kpis.productos_hoy_fallidos }}
                    </div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-1">
                        <Package class="h-3 w-3"/>Sin sincronizar
                    </div>
                    <div class="text-2xl font-bold mt-1 text-amber-600">{{ kpis.productos_sin_siigo_id }}</div>
                    <div class="text-[10px] text-surface-400 mt-0.5">productos activos aún sin siigo_id</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase text-surface-500 flex items-center gap-1">
                        <ListChecks class="h-3 w-3"/>7 días
                    </div>
                    <div class="text-2xl font-bold mt-1">{{ kpis.total_semana }}</div>
                    <div class="text-[10px] text-surface-400 mt-0.5">total operaciones sync</div>
                </div>
            </div>

            <!-- Cola en vivo + Última sync -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card p-5">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3 flex items-center gap-2">
                        <Clock class="h-4 w-4"/>Cola de envío ({{ cola.pendientes }} pendientes)
                    </div>
                    <div v-if="!cola.proximos.length" class="text-center py-6 text-surface-500 text-sm">
                        ✅ Cola vacía. Todos los jobs procesados.
                    </div>
                    <div v-else class="space-y-1.5">
                        <div v-for="p in cola.proximos" :key="p.id"
                             class="flex items-center gap-2 py-1.5 px-2 rounded hover:bg-surface-50 dark:hover:bg-surface-800 text-sm">
                            <span class="font-mono text-xs text-surface-500 w-16">#{{ p.producto_id }}</span>
                            <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-semibold uppercase',
                                            p.accion === 'crear' ? 'bg-blue-100 text-blue-700'
                                          : p.accion === 'actualizar' ? 'bg-purple-100 text-purple-700'
                                          : 'bg-red-100 text-red-700']">
                                {{ p.accion }}
                            </span>
                            <span class="text-xs text-surface-500 ml-auto">{{ p.disponible_en }}</span>
                            <span v-if="p.attempts > 0" class="text-[10px] text-amber-600 font-semibold">
                                {{ p.attempts }} intento{{ p.attempts > 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card p-5">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">
                        Última sincronización
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between border-b border-surface-100 pb-2">
                            <dt class="text-surface-500">Productos ↓ (pull)</dt>
                            <dd class="font-mono text-xs">{{ config.sync_productos_at || 'nunca' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-surface-100 pb-2">
                            <dt class="text-surface-500">Clientes ↓</dt>
                            <dd class="font-mono text-xs">{{ config.sync_clientes_at || 'nunca' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-surface-100 pb-2">
                            <dt class="text-surface-500">Catálogos ↓</dt>
                            <dd class="font-mono text-xs">{{ config.sync_catalogos_at || 'nunca' }}</dd>
                        </div>
                        <div class="flex justify-between pt-1">
                            <dt class="text-surface-500">Ambiente</dt>
                            <dd class="font-semibold uppercase">{{ config.ambiente || '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Últimos fallidos con botón reintentar -->
            <div class="card p-5" v-if="fallidos_recientes.length">
                <div class="text-xs uppercase tracking-widest font-bold text-red-600 mb-3 flex items-center gap-2">
                    <Ban class="h-4 w-4"/>Sincronizaciones fallidas recientes ({{ fallidos_recientes.length }})
                </div>
                <div class="space-y-1.5">
                    <div v-for="f in fallidos_recientes" :key="f.id"
                         class="flex items-center gap-3 py-2 px-3 rounded border border-red-100 dark:border-red-900/40 bg-red-50/30 dark:bg-red-900/10 text-sm">
                        <XCircle class="h-4 w-4 text-red-600 flex-shrink-0"/>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-xs">
                                Producto #{{ f.producto_id }} · acción <span class="uppercase">{{ f.accion }}</span>
                                <span v-if="f.http_status" class="ml-2 text-red-600 font-mono">HTTP {{ f.http_status }}</span>
                            </div>
                            <div class="text-[11px] text-surface-500 truncate">{{ f.mensaje }}</div>
                        </div>
                        <span class="text-[10px] text-surface-500">{{ f.hace }}</span>
                        <button @click="reintentar(f.id)" :disabled="reintentando === f.id"
                                class="px-3 py-1 text-xs font-semibold rounded bg-brand-600 hover:bg-brand-700 text-white inline-flex items-center gap-1 transition"
                                :class="reintentando === f.id ? 'opacity-50 cursor-wait' : ''">
                            <RefreshCw :class="['h-3 w-3', reintentando === f.id ? 'animate-spin' : '']"/>
                            Reintentar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bitácora reciente -->
            <div class="card p-5">
                <div class="text-xs uppercase tracking-widest font-bold text-brand-600 mb-3">
                    Últimas {{ logs.length }} operaciones
                </div>
                <div v-if="!logs.length" class="text-center py-10 text-surface-500 text-sm">
                    Sin sincronizaciones aún. Cuando llegue una acción a la cola aparecerá acá.
                </div>
                <table v-else class="w-full text-sm">
                    <thead class="text-[10px] text-surface-500 uppercase border-b">
                        <tr>
                            <th class="text-left p-2">Hora</th>
                            <th class="text-left p-2">Recurso</th>
                            <th class="text-left p-2">Estado</th>
                            <th class="text-right p-2">Nuevos</th>
                            <th class="text-right p-2">Actualizados</th>
                            <th class="text-right p-2">Errores</th>
                            <th class="text-right p-2">Duración</th>
                            <th class="text-left p-2">Mensaje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="l in logs" :key="l.id" class="hover:bg-surface-50 dark:hover:bg-surface-800">
                            <td class="p-2 text-xs text-surface-500 whitespace-nowrap">{{ l.hace }}</td>
                            <td class="p-2 font-mono text-xs">{{ l.recurso }}</td>
                            <td class="p-2"><span :class="['text-xs font-semibold uppercase', claseEstadoLog(l)]">{{ l.estado }}</span></td>
                            <td class="p-2 text-right">{{ l.nuevos || '—' }}</td>
                            <td class="p-2 text-right">{{ l.actualizados || '—' }}</td>
                            <td class="p-2 text-right" :class="l.errores > 0 ? 'text-red-600 font-bold' : ''">{{ l.errores || '—' }}</td>
                            <td class="p-2 text-right text-xs text-surface-500">{{ l.duracion_ms }}ms</td>
                            <td class="p-2 text-xs text-surface-500 truncate max-w-md" :title="l.mensaje">{{ l.mensaje }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AppLayout>
</template>
