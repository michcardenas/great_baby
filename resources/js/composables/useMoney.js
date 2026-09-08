// QA-D Bloque2: helper único de formato moneda COP.
// Antes había 4 formatos distintos entre páginas ('$'+toLocaleString vs Intl 'currency').
// Colombia + 'currency' imprime "COP 1.234" (no "$1.234"). Usamos formato manual con "$".
export function useMoney() {
    const money = (n) => {
        const num = Number(n || 0);
        return '$' + num.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    };
    return { money };
}
