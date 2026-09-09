/**
 * Re-audit M5 R2 PATRÓN γ · fechas del filtro en zona America/Bogota.
 *
 * Problema fixeado: `new Date().toISOString().slice(0,10)` devuelve UTC. Entre
 * las 19:00 y 24:00 hora Bogotá (UTC-5) el chip "Hoy" saltaba al día siguiente
 * — la tabla salía vacía cada tarde-noche. 5 horas de fallo garantizado
 * diariamente. Este composable centraliza el cálculo y evita `toISOString` en
 * zonas comparadas con columnas DATE del backend (que están en Colombia).
 */

const FMT = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Bogota',
    year: 'numeric', month: '2-digit', day: '2-digit',
});

const bogotaYmd = (d = new Date()) => FMT.format(d); // 'YYYY-MM-DD'

/**
 * Devuelve un pedazo de fecha Colombia sin cruzar por toISOString().
 * @param {Date} d
 * @returns {{year:number, month:number, day:number}} month 1-12.
 */
const bogotaParts = (d = new Date()) => {
    const [y, m, day] = bogotaYmd(d).split('-').map(Number);
    return { year: y, month: m, day };
};

export function useBogotaDate() {
    return {
        hoy: () => bogotaYmd(),
        ymd: bogotaYmd,
        /** Primer día del mes actual en Bogotá. */
        inicioMes: () => {
            const p = bogotaParts();
            return `${p.year}-${String(p.month).padStart(2, '0')}-01`;
        },
        /** Primer día del mes anterior en Bogotá (no depende de TZ local del navegador). */
        inicioMesAnterior: () => {
            const p = bogotaParts();
            const y = p.month === 1 ? p.year - 1 : p.year;
            const m = p.month === 1 ? 12 : p.month - 1;
            return `${y}-${String(m).padStart(2, '0')}-01`;
        },
        /**
         * Último día del mes anterior en Bogotá.
         *
         * R4 UX-C1 / FUNC-C1 · REGRESIÓN de ronda 3 corregida.
         * Antes: `new Date(Date.UTC(p.year, p.month-1, 0))` = medianoche UTC del
         * último día → formateado en Bogotá (UTC-5) devolvía 19:00 del día
         * previo → chip "Mes anterior" perdía el día 31 sistemáticamente.
         *
         * Fix: aritmética entera sin Date, con tabla de días por mes + bisiesto
         * gregoriano — nunca toca el timezone del navegador.
         */
        finMesAnterior: () => {
            const p = bogotaParts();
            const y = p.month === 1 ? p.year - 1 : p.year;
            const m = p.month === 1 ? 12 : p.month - 1;
            const bisiesto = (y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0));
            const dias = [31, bisiesto ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][m - 1];
            return `${y}-${String(m).padStart(2, '0')}-${String(dias).padStart(2, '0')}`;
        },
        /** Primer día del trimestre actual. */
        inicioTrimestre: () => {
            const p = bogotaParts();
            const q = Math.floor((p.month - 1) / 3);
            const mesInicio = q * 3 + 1;
            return `${p.year}-${String(mesInicio).padStart(2, '0')}-01`;
        },
        /** 1 de enero del año actual. */
        inicioYtd: () => {
            const p = bogotaParts();
            return `${p.year}-01-01`;
        },
    };
}
