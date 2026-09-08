<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import { Chart, DoughnutController, ArcElement, Tooltip, Legend } from 'chart.js';
Chart.register(DoughnutController, ArcElement, Tooltip, Legend);

const props = defineProps({
    labels: { type: Array, required: true },
    values: { type: Array, required: true },
    colors: { type: Array, required: true },
    height: { type: String, default: '240px' },
});

const canvas = ref(null);
let chart = null;

const esDark = () => document.documentElement.classList.contains('dark')
    || window.matchMedia?.('(prefers-color-scheme: dark)').matches;

const render = () => {
    if (!canvas.value) return;
    const dark = esDark();
    if (chart) { chart.destroy(); chart = null; }
    chart = new Chart(canvas.value, {
        type: 'doughnut',
        data: {
            labels: props.labels,
            datasets: [{ data: props.values, backgroundColor: props.colors, borderColor: dark ? '#0f172a' : '#ffffff', borderWidth: 2 }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '65%',
            animation: false,
            plugins: {
                legend: { position: 'right', labels: { color: dark ? '#94a3b8' : '#475569', font: { size: 10 }, padding: 8, boxWidth: 12 } },
                tooltip: { backgroundColor: dark ? 'rgba(15,23,42,.95)' : 'rgba(255,255,255,.98)', titleColor: '#f59e0b', bodyColor: dark ? '#e2e8f0' : '#1e293b', borderColor: '#f59e0b', borderWidth: 1 },
            },
        },
    });
};

const actualizarDatos = () => {
    if (!chart) { render(); return; }
    chart.data.labels = props.labels;
    chart.data.datasets[0].data = props.values;
    chart.data.datasets[0].backgroundColor = props.colors;
    chart.update('none');
};

const dataHash = () => JSON.stringify([props.labels, props.values, props.colors]);
let ultimoHash = '';

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
watch(() => [props.labels, props.values, props.colors], () => {
    const h = dataHash();
    if (h === ultimoHash) return;
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
