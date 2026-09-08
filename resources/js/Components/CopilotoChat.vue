<script setup>
import { ref, nextTick } from 'vue';
import { Sparkles, Send, X, Bot } from 'lucide-vue-next';

const abierto = ref(false);
const pregunta = ref('');
const historial = ref([]);
const enviando = ref(false);
const chatEl = ref(null);
const inputEl = ref(null);

const toggle = () => {
    abierto.value = !abierto.value;
    if (abierto.value) nextTick(() => inputEl.value?.focus());
};

const scroll = () => nextTick(() => { chatEl.value && (chatEl.value.scrollTop = chatEl.value.scrollHeight); });

const enviar = async () => {
    const q = pregunta.value.trim();
    if (!q || enviando.value) return;
    historial.value.push({ rol: 'user', texto: q });
    pregunta.value = '';
    enviando.value = true;
    scroll();
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch('/app/copiloto', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify({ pregunta: q }),
        });
        const data = await res.json();
        historial.value.push({ rol: 'ai', texto: data.respuesta, fallback: data.fallback });
    } catch (e) {
        historial.value.push({ rol: 'ai', texto: '⚠ Error de red', fallback: true });
    } finally {
        enviando.value = false;
        scroll();
    }
};

const sugerencias = [
    '¿Cuánto vendí este mes?',
    '¿Qué garantías tengo abiertas?',
    '¿Cuál es mi cartera vencida?',
    '¿Cuáles son las top 3 ciudades este mes?',
    '¿Cuántos pedidos B2B pendientes hay?',
];
const usarSugerencia = (s) => { pregunta.value = s; enviar(); };
</script>

<template>
    <!-- Botón flotante -->
    <button v-if="!abierto" @click="toggle"
        class="fixed bottom-6 right-6 z-40 h-14 w-14 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-2xl hover:scale-110 transition-transform flex items-center justify-center"
        title="Preguntarle al copiloto">
        <Sparkles class="h-6 w-6"/>
    </button>

    <!-- Panel de chat -->
    <div v-if="abierto" class="fixed bottom-6 right-6 z-40 w-full max-w-md bg-white dark:bg-surface-900 rounded-xl shadow-2xl border border-surface-200 dark:border-surface-800 flex flex-col" style="max-height: 70vh;">
        <div class="p-4 border-b border-surface-200 dark:border-surface-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white">
                    <Bot class="h-4 w-4"/>
                </div>
                <div>
                    <div class="font-bold text-sm">Copiloto GB</div>
                    <div class="text-[10px] text-surface-500">Pregúntame lo que quieras del negocio</div>
                </div>
            </div>
            <button @click="toggle" class="btn-ghost p-1"><X class="h-4 w-4"/></button>
        </div>

        <div ref="chatEl" class="flex-1 overflow-y-auto p-4 space-y-3">
            <div v-if="!historial.length" class="space-y-2">
                <p class="text-xs text-surface-500">Ejemplos:</p>
                <button v-for="s in sugerencias" :key="s" @click="usarSugerencia(s)"
                    class="w-full text-left text-sm p-2 rounded bg-surface-50 hover:bg-brand-50 dark:bg-surface-800 dark:hover:bg-brand-900/30 text-surface-700 dark:text-surface-300">
                    💡 {{ s }}
                </button>
            </div>
            <div v-for="(m, i) in historial" :key="i"
                :class="['rounded-lg p-3 text-sm whitespace-pre-wrap',
                    m.rol === 'user' ? 'bg-brand-600 text-white ml-8' : 'bg-surface-100 dark:bg-surface-800 mr-8']">
                <div v-if="m.rol === 'ai' && m.fallback" class="text-[10px] text-amber-600 font-bold mb-1">MODO SIN IA</div>
                {{ m.texto }}
            </div>
            <div v-if="enviando" class="bg-surface-100 dark:bg-surface-800 rounded-lg p-3 mr-8 text-sm text-surface-500">Pensando...</div>
        </div>

        <div class="p-3 border-t border-surface-200 dark:border-surface-800 flex gap-2">
            <input ref="inputEl" v-model="pregunta" @keydown.enter="enviar"
                :disabled="enviando" placeholder="Escribe tu pregunta..."
                class="input flex-1 text-sm"/>
            <button @click="enviar" :disabled="!pregunta.trim() || enviando" class="btn-primary p-2">
                <Send class="h-4 w-4"/>
            </button>
        </div>
    </div>
</template>
