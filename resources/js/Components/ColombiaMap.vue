<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    puntos: { type: Array, required: true }, // [{ciudad, total, lat, lng}]
    height: { type: String, default: '320px' },
});

const el = ref(null);
let mapa = null;
let capas = [];

const render = () => {
    if (!el.value) return;

    if (!mapa) {
        mapa = L.map(el.value, {
            center: [4.6, -74.1], zoom: 5, zoomControl: true,
            attributionControl: false, preferCanvas: true,
        });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap',
        }).addTo(mapa);
        // Corrige el bug del tamaño 0 cuando el contenedor no está listo
        setTimeout(() => {
            mapa.invalidateSize();
            mapa.setView([4.6, -74.1], 5);
        }, 100);
    }

    capas.forEach(c => mapa.removeLayer(c));
    capas = [];

    const maxTotal = Math.max(1, ...props.puntos.map(p => p.total));

    props.puntos.forEach(p => {
        const radio = 8 + Math.round((p.total / maxTotal) * 26);
        const marker = L.circleMarker([p.lat, p.lng], {
            radius: radio,
            color: '#f59e0b', weight: 2, fillColor: '#f59e0b',
            fillOpacity: 0.45,
        }).bindTooltip(`<strong>${p.ciudad}</strong><br>${p.total} pedidos`, {
            direction: 'top', className: 'leaflet-gb-tooltip',
        }).addTo(mapa);
        capas.push(marker);
    });
};

onMounted(render);
watch(() => props.puntos, render, { deep: true });
onBeforeUnmount(() => { mapa?.remove(); mapa = null; });
</script>

<template>
    <div :style="{ height }" ref="el" class="rounded-lg overflow-hidden"></div>
</template>

<style>
.leaflet-gb-tooltip {
    background: #0f172a !important;
    color: #f59e0b !important;
    border: 1px solid #b45309 !important;
    font-size: .85rem;
    padding: .4rem .6rem !important;
    border-radius: .35rem !important;
    box-shadow: 0 4px 12px rgba(0,0,0,.5);
}
</style>
