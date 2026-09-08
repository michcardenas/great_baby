<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import {
    Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale,
    Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler);

const props = defineProps({
    labels: { type: Array, required: true },
    datasets: { type: Array, required: true }, // [{label, data, color}]
    height: { type: String, default: '240px' },
});

const canvas = ref(null);
let chart = null;

const buildDatasets = () => props.datasets.map(d => ({
    label: d.label,
    data: d.data,
    borderColor: d.color,
    backgroundColor: d.color + '26',
    tension: 0.35, fill: true, borderWidth: 2, pointRadius: 4,
}));

const render = () => {
    if (!canvas.value) return;
    // Detectar dark: si .dark está en html o media prefers dark
    const isDark = document.documentElement.classList.contains('dark')
        || window.matchMedia?.('(prefers-color-scheme: dark)').matches;
    const tickColor = isDark ? '#94a3b8' : '#475569';
    const gridColor = isDark ? 'rgba(148,163,184,.1)' : 'rgba(100,116,139,.15)';
    const tooltipBg = isDark ? 'rgba(15,23,42,.95)' : 'rgba(255,255,255,.98)';
    const tooltipBody = isDark ? '#e2e8f0' : '#1e293b';

    if (chart) { chart.destroy(); chart = null; }
    chart = new Chart(canvas.value, {
        type: 'line',
        data: { labels: props.labels, datasets: buildDatasets() },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: false, // evita re-animar en cada refresh
            plugins: {
                legend: { labels: { color: tickColor, font: { size: 11 } } },
                tooltip: { backgroundColor: tooltipBg, titleColor: '#f59e0b', bodyColor: tooltipBody, borderColor: '#f59e0b', borderWidth: 1 },
            },
            scales: {
                x: { ticks: { color: tickColor }, grid: { color: gridColor } },
                y: { ticks: { color: tickColor }, grid: { color: gridColor }, beginAtZero: true },
            },
        },
    });
};

// Actualización in-place cuando cambian datos (evita destroy+create + parpadeo).
const actualizarDatos = () => {
    if (!chart) { render(); return; }
    chart.data.labels = props.labels;
    chart.data.datasets = buildDatasets();
    chart.update('none'); // sin animar
};

const dataHash = () => JSON.stringify([props.labels, props.datasets.map(d => [d.label, d.data])]);
let ultimoHash = '';

// Reacciona a cambios de tema en vivo (system o clase .dark en <html>).
let mql = null;
let observer = null;
const relRender = () => render();

onMounted(() => {
    render();
    ultimoHash = dataHash();
    if (typeof window !== 'undefined' && window.matchMedia) {
        mql = window.matchMedia('(prefers-color-scheme: dark)');
        mql.addEventListener?.('change', relRender);
    }
    if (typeof MutationObserver !== 'undefined') {
        observer = new MutationObserver(relRender);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    }
});
watch(() => [props.labels, props.datasets], () => {
    const h = dataHash();
    if (h === ultimoHash) return; // datos idénticos → no re-render
    ultimoHash = h;
    actualizarDatos();
}, { deep: true });
onBeforeUnmount(() => {
    chart?.destroy();
    mql?.removeEventListener?.('change', relRender);
    observer?.disconnect();
});
</script>

<template>
    <div :style="{ height }" class="relative">
        <canvas ref="canvas"></canvas>
    </div>
</template>
