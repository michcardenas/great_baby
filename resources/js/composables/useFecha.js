/**
 * Re-audit M5 R2 UX-A3 · formato de fecha localizado ES-CO.
 * Antes se renderizaba ISO cruda (`2026-09-15`); Aracely quiere `15/sep/2026`.
 */
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/**
 * Convierte 'YYYY-MM-DD' → '15/sep/2026'. Acepta también Date; devuelve '—' si vacío.
 */
export function fechaCorta(v) {
    if (!v) return '—';
    const s = String(v);
    // Match ISO 'YYYY-MM-DD' (con o sin tiempo) sin instanciar Date (evita TZ).
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) {
        const [, y, mo, d] = m;
        return `${parseInt(d, 10)}/${MESES[parseInt(mo, 10) - 1]}/${y}`;
    }
    return s;
}

export function useFecha() {
    return { fechaCorta };
}
