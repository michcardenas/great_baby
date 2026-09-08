import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// AudioContext único por sesión de página (Chrome/Firefox limitan a ~6 antes de bloquear).
let ctxSingleton = null;
const getCtx = () => {
    if (ctxSingleton) return ctxSingleton;
    try {
        ctxSingleton = new (window.AudioContext || window.webkitAudioContext)();
    } catch (e) { return null; }
    return ctxSingleton;
};

// El primer AudioContext queda 'suspended' hasta que haya un gesto del usuario.
// Enganchamos un listener global que hace resume() al primer pointerdown/keydown y se desmonta.
if (typeof window !== 'undefined') {
    const desbloquear = () => {
        const c = getCtx();
        if (c && c.state === 'suspended') c.resume().catch(() => {});
        window.removeEventListener('pointerdown', desbloquear);
        window.removeEventListener('keydown', desbloquear);
    };
    window.addEventListener('pointerdown', desbloquear, { once: false });
    window.addEventListener('keydown', desbloquear, { once: false });
}

// Voz es-CO cargada una vez.
let vozEs = null;
const cargarVoz = () => {
    if (typeof speechSynthesis === 'undefined') return;
    const voces = speechSynthesis.getVoices();
    vozEs = voces.find(v => v.lang === 'es-CO')
         || voces.find(v => v.lang === 'es-MX')
         || voces.find(v => v.lang?.startsWith('es'))
         || null;
};
if (typeof speechSynthesis !== 'undefined') {
    cargarVoz();
    speechSynthesis.onvoiceschanged = cargarVoz;
}

export function useSonido() {
    // Mute namespaced por usuario (evita que operarios se compartan la preferencia en tablet compartida).
    const page = usePage();
    const userId = computed(() => page.props.auth?.user?.id ?? 'anon');
    const muteKey = computed(() => `gb.mute.${userId.value}`);

    const readMute = () => {
        try { return localStorage.getItem(muteKey.value) === '1'; }
        catch (e) { return false; }
    };
    const mute = ref(readMute());
    watch(userId, () => { mute.value = readMute(); });
    watch(mute, (v) => {
        try { localStorage.setItem(muteKey.value, v ? '1' : '0'); } catch (e) {}
    });

    const beep = (tipo = 'ok') => {
        if (mute.value) return;
        const ctx = getCtx();
        if (! ctx) return;
        try {
            if (ctx.state === 'suspended') ctx.resume().catch(() => {});
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type = 'sine';
            const freqs = { ok: 880, warn: 440, error: 220, alert: 660 };
            osc.frequency.value = freqs[tipo] ?? 880;
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            osc.start();
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) {}
    };

    const hablar = (texto, opts = {}) => {
        if (mute.value) return;
        if (typeof speechSynthesis === 'undefined') return;
        try {
            const u = new SpeechSynthesisUtterance(texto);
            if (vozEs) u.voice = vozEs;
            u.lang = 'es-CO';
            u.rate = opts.tasa ?? 1.1;
            u.pitch = opts.tono ?? 1;
            u.volume = 1;
            speechSynthesis.cancel();
            speechSynthesis.speak(u);
        } catch (e) {}
    };

    const toggleMute = () => (mute.value = !mute.value);

    return { beep, hablar, mute, toggleMute };
}
