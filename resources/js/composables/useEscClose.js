import { watch, onBeforeUnmount } from 'vue';

// Re-audit UX#5 · registra un listener global `keydown Escape` que cierra el
// modal cuando `openRef.value === true`. Se auto-limpia al desmontar.
// Uso:
//   const modal = ref(false);
//   useEscClose(modal);
export function useEscClose(openRef) {
    const handler = (e) => {
        if (e.key === 'Escape' && openRef.value) {
            openRef.value = false;
        }
    };

    watch(openRef, (isOpen) => {
        if (isOpen) {
            window.addEventListener('keydown', handler);
        } else {
            window.removeEventListener('keydown', handler);
        }
    }, { immediate: true });

    onBeforeUnmount(() => window.removeEventListener('keydown', handler));
}
