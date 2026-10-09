<script setup>
/**
 * Pide un texto antes de confirmar una acción. Reemplaza al `prompt()` nativo.
 *
 * El `prompt()` queda bloqueado dentro del iframe de la app de escritorio: la
 * llamada devuelve null al instante, así que anular una OC, una toma física o
 * un traslado no hacía absolutamente nada y sin ningún mensaje. Encima en
 * celular abre el cuadro del sistema, que no deja ver el contexto de lo que se
 * está anulando.
 *
 * Acá además se valida el mínimo de caracteres mientras se escribe, en vez de
 * dejar escribir, aceptar y recién entonces avisar que era muy corto.
 *
 * Uso:
 *   const prompt = ref(null);
 *   prompt.value = {
 *       titulo: `Anular la OC ${o.numero}`,
 *       mensaje: 'Queda registrado quién la anuló y cuándo.',
 *       etiqueta: 'Motivo de la anulación',
 *       minimo: 10,
 *       color: 'rose',
 *       textoConfirmar: 'Anular',
 *       onConfirmar: (motivo) => { … },
 *   };
 *   <AppPromptModal :cfg="prompt" @cerrar="prompt = null"/>
 */
import { computed, ref, watch, nextTick } from 'vue';

const props = defineProps({
    cfg: { type: Object, default: null },
});
const emit = defineEmits(['cerrar']);

const COLORES = {
    emerald: { title: 'text-emerald-700', btn: 'bg-emerald-600 hover:bg-emerald-700' },
    rose:    { title: 'text-rose-700',    btn: 'bg-rose-600 hover:bg-rose-700' },
    amber:   { title: 'text-amber-700',   btn: 'bg-amber-600 hover:bg-amber-700' },
    sky:     { title: 'text-sky-700',     btn: 'bg-sky-600 hover:bg-sky-700' },
    brand:   { title: 'text-brand-700',   btn: 'bg-brand-600 hover:bg-brand-700' },
};

const valor = ref('');
const campo = ref(null);

const paleta = computed(() => COLORES[props.cfg?.color] || COLORES.brand);
const minimo = computed(() => Number(props.cfg?.minimo || 0));
const faltan = computed(() => Math.max(0, minimo.value - valor.value.trim().length));
const puedeConfirmar = computed(() => faltan.value === 0 && valor.value.trim().length > 0);

watch(() => props.cfg, async (cfg) => {
    valor.value = cfg?.valorInicial || '';
    if (cfg) {
        await nextTick();
        campo.value?.focus();
    }
});

const confirmar = () => {
    if (! puedeConfirmar.value) return;
    props.cfg.onConfirmar(valor.value.trim());
};
</script>

<template>
    <div v-if="cfg"
         class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
         @click.self="emit('cerrar')"
         @keydown.esc="emit('cerrar')">
        <div class="bg-white dark:bg-surface-800 rounded-xl shadow-xl max-w-md w-full m-4 p-5 space-y-4">
            <h2 class="font-bold text-lg" :class="paleta.title">{{ cfg.titulo }}</h2>
            <p v-if="cfg.mensaje" class="text-sm text-surface-600 whitespace-pre-line">{{ cfg.mensaje }}</p>

            <div>
                <label class="label">{{ cfg.etiqueta || 'Motivo' }}</label>
                <textarea ref="campo"
                          v-model="valor"
                          rows="3"
                          class="input"
                          :placeholder="cfg.placeholder || ''"></textarea>
                <p v-if="minimo" class="mt-1 text-xs"
                   :class="faltan ? 'text-amber-600' : 'text-surface-500'">
                    {{ faltan ? `Faltan ${faltan} caracter${faltan === 1 ? '' : 'es'}` : 'Listo' }}
                    · mínimo {{ minimo }}
                </p>
            </div>

            <!-- Igual que en AppConfirmModal: 44px de alto en celular. -->
            <div class="flex items-center gap-2 justify-end pt-3 border-t">
                <button @click="emit('cerrar')"
                        class="flex-1 sm:flex-none text-sm px-4 min-h-[44px] sm:min-h-0 sm:py-1.5 rounded border border-surface-300 hover:bg-surface-50">
                    Cancelar
                </button>
                <button @click="confirmar"
                        :disabled="! puedeConfirmar"
                        :class="['flex-1 sm:flex-none text-sm px-4 min-h-[44px] sm:min-h-0 sm:py-1.5 rounded text-white disabled:opacity-50 disabled:cursor-not-allowed', paleta.btn]">
                    {{ cfg.textoConfirmar || 'Confirmar' }}
                </button>
            </div>
        </div>
    </div>
</template>
