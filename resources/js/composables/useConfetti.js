import confetti from 'canvas-confetti';

/**
 * Guard robusto: localStorage puede tirar en modo privado / kiosk-mode / lleno.
 * Nunca debe romper el flujo del confirmar (fix UX #8 auditor).
 */
const lsGet = (k, def = null) => {
    try { return localStorage.getItem(k) ?? def; } catch (e) { return def; }
};
const lsSet = (k, v) => {
    try { localStorage.setItem(k, v); } catch (e) {}
};
const lsRemove = (k) => {
    try { localStorage.removeItem(k); } catch (e) {}
};
const lsKeys = () => {
    try { return Object.keys(localStorage); } catch (e) { return []; }
};

export function useConfetti() {
    const dispararConfetti = (opts = {}) => {
        try {
            if (typeof window !== 'undefined' && window.gbMute) return;

            // Limpieza defensiva de keys viejas (idempotente, catch-all).
            const hoyKey = 'gb.confeti.' + new Date().toISOString().slice(0, 10);
            for (const k of lsKeys()) {
                if (k.startsWith('gb.confeti.') && k !== hoyKey) lsRemove(k);
            }

            const contador = parseInt(lsGet(hoyKey, '0'), 10) + 1;
            lsSet(hoyKey, String(contador));

            if (!opts.force && contador % 5 !== 0) return;

            confetti({
                particleCount: opts.particleCount ?? 100,
                spread: opts.spread ?? 70,
                origin: { y: 0.6 },
                colors: ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6'],
            });
        } catch (e) {
            // Nunca propagar — el confirmar del empaque no debe fallar por confetti.
            if (typeof console !== 'undefined') console.warn('[useConfetti] guard', e?.message);
        }
    };

    return { dispararConfetti };
}
