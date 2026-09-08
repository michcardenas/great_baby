<script setup>
import { reactive, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Minus, ShoppingCart } from 'lucide-vue-next';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    producto: { type: Object, required: true },
    variantes: { type: Array, required: true },
});

const cantidades = reactive({});
props.variantes.forEach(v => { cantidades[v.id] = 0; });

const money = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(n || 0);

const inc = (v) => { if (v.precio > 0) cantidades[v.id] = (cantidades[v.id] || 0) + 1; };
const dec = (v) => { cantidades[v.id] = Math.max(0, (cantidades[v.id] || 0) - 1); };

const totalItems = computed(() => Object.values(cantidades).reduce((s, c) => s + (c || 0), 0));

const agregarAlCarrito = () => {
    if (totalItems.value === 0) return;
    let carrito = [];
    try { carrito = JSON.parse(sessionStorage.getItem('portal.carrito') || '[]'); } catch {}
    for (const v of props.variantes) {
        const cant = cantidades[v.id] || 0;
        if (cant === 0) continue;
        const existe = carrito.find(i => i.variante_id === v.id);
        if (existe) { existe.cantidad += cant; }
        else {
            carrito.push({
                variante_id: v.id,
                cantidad: cant,
                precio: v.precio,
                producto: props.producto.nombre,
                referencia: props.producto.referencia,
                detalle: [v.color, v.talla].filter(Boolean).join(' · '),
            });
        }
        cantidades[v.id] = 0;
    }
    sessionStorage.setItem('portal.carrito', JSON.stringify(carrito));
    window.dispatchEvent(new CustomEvent('portal:carrito-actualizado'));
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
</script>

<template>
    <Head :title="producto.nombre"/>
    <PortalLayout>
        <div class="max-w-4xl mx-auto space-y-4">
            <Link href="/portal/catalogo" class="btn-ghost inline-flex items-center gap-1 text-sm">
                <ArrowLeft class="h-4 w-4"/> Volver al catálogo
            </Link>

            <div class="card p-6">
                <div class="text-xs font-mono text-surface-500">{{ producto.referencia }}</div>
                <h1 class="text-2xl font-bold mt-1">{{ producto.nombre }}</h1>
                <p v-if="producto.descripcion" class="text-sm text-surface-600 dark:text-surface-400 mt-2">{{ producto.descripcion }}</p>
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs uppercase tracking-widest font-bold text-brand-600">Variantes</div>
                    <div v-if="totalItems > 0" class="text-sm text-surface-600">{{ totalItems }} unidades seleccionadas</div>
                </div>

                <div class="divide-y divide-surface-100 dark:divide-surface-800">
                    <div v-for="v in variantes" :key="v.id" class="py-3 flex items-center gap-4">
                        <div class="flex-1">
                            <div class="font-medium">
                                <span v-if="v.color">{{ v.color }}</span>
                                <span v-if="v.color && v.talla" class="text-surface-400"> · </span>
                                <span v-if="v.talla">Talla {{ v.talla }}</span>
                                <span v-if="!v.color && !v.talla" class="text-surface-500">Única</span>
                            </div>
                            <div class="text-xs text-surface-500 font-mono">{{ v.codigo_barras }}</div>
                        </div>
                        <div class="text-right w-28">
                            <div v-if="v.precio > 0" class="font-bold">{{ money(v.precio) }}</div>
                            <div v-else class="text-xs text-amber-600">Sin precio</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="dec(v)" :disabled="!cantidades[v.id]" class="btn-ghost p-1.5 disabled:opacity-30">
                                <Minus class="h-4 w-4"/>
                            </button>
                            <input v-model.number="cantidades[v.id]" type="number" min="0" max="9999"
                                class="input w-16 text-center text-sm"/>
                            <button @click="inc(v)" :disabled="v.precio <= 0" class="btn-ghost p-1.5 disabled:opacity-30">
                                <Plus class="h-4 w-4"/>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-surface-200 dark:border-surface-800 mt-4">
                    <button @click="agregarAlCarrito" :disabled="totalItems === 0" class="btn-primary w-full disabled:opacity-50 disabled:cursor-not-allowed">
                        <ShoppingCart class="h-4 w-4"/>
                        Agregar {{ totalItems || '' }} al carrito
                    </button>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>
