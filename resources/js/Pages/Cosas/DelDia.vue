<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Sunrise, Package, ShieldCheck, AlertCircle, UserX, Ghost, Boxes, Wallet } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    generado_at: String,
    pedidosB2BLentos: Array,
    garantiasSinDecidir: Array,
    facturasVencidas: Array,
    clientesDormidos: Array,
    fantasma: Array,
    criticos: Array,
    wallet: Object,
});

const money = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
const totalItems = props.pedidosB2BLentos.length + props.garantiasSinDecidir.length + props.facturasVencidas.length + props.clientesDormidos.length + props.fantasma.length + props.criticos.length + (props.wallet.sin_cobro > 0 ? 1 : 0);
</script>

<template>
    <Head title="🌅 Cosas del día"/>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <Sunrise class="h-7 w-7 text-amber-500"/>
                        Cosas del día
                    </h1>
                    <p class="text-sm text-surface-500 mt-1">Todo lo que necesita tu atención HOY · actualizado {{ generado_at }}</p>
                </div>
                <div v-if="totalItems === 0" class="px-4 py-2 rounded-lg bg-emerald-500/15 text-emerald-800 dark:text-emerald-300 text-sm font-bold">
                    ✨ Todo al día — sin pendientes urgentes
                </div>
                <div v-else class="px-4 py-2 rounded-lg bg-amber-500/15 text-amber-800 dark:text-amber-300 text-sm font-bold">
                    {{ totalItems }} cosas para revisar
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Pedidos B2B lentos -->
                <div v-if="pedidosB2BLentos.length" class="card p-4 border-l-4 border-blue-500">
                    <div class="flex items-center gap-2 mb-3">
                        <Package class="h-5 w-5 text-blue-600"/>
                        <div class="font-bold">Pedidos B2B esperando +24h ({{ pedidosB2BLentos.length }})</div>
                    </div>
                    <div class="space-y-1">
                        <Link v-for="p in pedidosB2BLentos" :key="p.id" :href="p.url" class="flex items-center justify-between text-sm hover:bg-surface-50 rounded p-2">
                            <div>
                                <span class="font-mono font-bold text-brand-600">{{ p.numero }}</span>
                                <span class="text-surface-500 ml-2">{{ p.cliente }}</span>
                            </div>
                            <div class="text-right">
                                <div class="font-bold">{{ money(p.total) }}</div>
                                <div class="text-xs text-red-600">hace {{ p.hace_horas }}h</div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Garantías sin decidir -->
                <div v-if="garantiasSinDecidir.length" class="card p-4 border-l-4 border-amber-500">
                    <div class="flex items-center gap-2 mb-3">
                        <ShieldCheck class="h-5 w-5 text-amber-600"/>
                        <div class="font-bold">Garantías sin decidir ({{ garantiasSinDecidir.length }})</div>
                    </div>
                    <div class="space-y-1">
                        <Link v-for="g in garantiasSinDecidir" :key="g.id" :href="g.url" class="flex items-center justify-between text-sm hover:bg-surface-50 rounded p-2">
                            <div>
                                <span class="font-mono font-bold text-brand-600">{{ g.numero }}</span>
                                <span class="text-surface-500 ml-2">{{ g.cliente }}</span>
                            </div>
                            <div class="text-right">
                                <div v-if="g.plazo_vencido" class="text-xs font-bold text-red-600">⏰ plazo vencido</div>
                                <div class="text-xs text-surface-500">hace {{ g.hace_dias }}d</div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Facturas vencidas -->
                <div v-if="facturasVencidas.length" class="card p-4 border-l-4 border-red-500">
                    <div class="flex items-center gap-2 mb-3">
                        <AlertCircle class="h-5 w-5 text-red-600"/>
                        <div class="font-bold">Facturas vencidas — top saldo ({{ facturasVencidas.length }})</div>
                    </div>
                    <div class="space-y-1">
                        <Link v-for="f in facturasVencidas" :key="f.id" :href="f.url" class="flex items-center justify-between text-sm hover:bg-surface-50 rounded p-2">
                            <div class="min-w-0 flex-1">
                                <span class="font-mono font-bold">{{ f.numero }}</span>
                                <div class="text-xs text-surface-500 truncate">{{ f.cliente }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-red-600">{{ money(f.saldo) }}</div>
                                <div class="text-xs text-surface-500">{{ Math.abs(f.dias_vencida) }}d vencida</div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Clientes dormidos VIP -->
                <div v-if="clientesDormidos.length" class="card p-4 border-l-4 border-purple-500">
                    <div class="flex items-center gap-2 mb-3">
                        <UserX class="h-5 w-5 text-purple-600"/>
                        <div class="font-bold">Clientes VIP/frecuente dormidos ({{ clientesDormidos.length }})</div>
                    </div>
                    <div class="space-y-1">
                        <Link v-for="c in clientesDormidos" :key="c.id" :href="c.url" class="flex items-center justify-between text-sm hover:bg-surface-50 rounded p-2">
                            <div>
                                <div class="font-medium">{{ c.nombre }}</div>
                                <div class="text-xs text-surface-500">{{ c.segmento }} · última: {{ c.ultima_compra }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-bold text-purple-600">{{ c.dias_sin_comprar }}d</div>
                                <a v-if="c.telefono" :href="`https://wa.me/57${c.telefono.replace(/\D/g,'')}`" target="_blank" @click.stop class="text-xs text-emerald-600 hover:underline">WhatsApp</a>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Stock crítico -->
                <div v-if="criticos.length" class="card p-4 border-l-4 border-orange-500">
                    <div class="flex items-center gap-2 mb-3">
                        <Boxes class="h-5 w-5 text-orange-600"/>
                        <div class="font-bold">Stock crítico bajo mínimo ({{ criticos.length }})</div>
                    </div>
                    <div class="space-y-1">
                        <Link v-for="(k, i) in criticos" :key="i" :href="k.url" class="flex items-center justify-between text-sm hover:bg-surface-50 rounded p-2">
                            <div>
                                <span class="font-mono text-xs">{{ k.sku }}</span>
                                <div class="text-xs text-surface-500 truncate">{{ k.producto }}</div>
                            </div>
                            <div class="text-xs text-orange-600 font-bold">mín {{ k.minimo }}</div>
                        </Link>
                    </div>
                </div>

                <!-- Wallet Dropi -->
                <div v-if="wallet.sin_cobro > 0" class="card p-4 border-l-4 border-brand-500">
                    <div class="flex items-center gap-2 mb-3">
                        <Wallet class="h-5 w-5 text-brand-600"/>
                        <div class="font-bold">Dinero Dropi sin cobrar</div>
                    </div>
                    <Link href="/app/dropi/discrepancias" class="block p-3 hover:bg-surface-50 rounded">
                        <div class="text-3xl font-bold text-red-600">{{ money(wallet.monto_faltante) }}</div>
                        <div class="text-sm text-surface-500">{{ wallet.sin_cobro }} pedidos pagados sin movimiento en wallet</div>
                    </Link>
                </div>

                <!-- Mercancía fantasma -->
                <div v-if="fantasma.length" class="card p-4 border-l-4 border-pink-500">
                    <div class="flex items-center gap-2 mb-3">
                        <Ghost class="h-5 w-5 text-pink-600"/>
                        <div class="font-bold">Mercancía fantasma Dropi ({{ fantasma.length }})</div>
                    </div>
                    <p class="text-xs text-surface-500 mb-2">Pedidos marcados devueltos que nunca llegaron a bodega física.</p>
                    <div class="text-xs space-y-1">
                        <div v-for="(f, i) in fantasma" :key="i" class="p-2 bg-surface-50 rounded font-mono">
                            {{ JSON.stringify(f).slice(0, 100) }}...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
