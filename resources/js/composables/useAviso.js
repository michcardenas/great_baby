/**
 * Avisos al usuario sin usar `alert()`.
 *
 * El `alert()` nativo tiene dos problemas comprobados: queda bloqueado dentro
 * del iframe de la app de escritorio —el aviso simplemente no aparece y la
 * persona cree que no pasó nada— y en celular abre el diálogo del sistema
 * encima del diseño, con un botón diminuto.
 *
 * Acá se manda un evento que el AppLayout ya escucha y pinta como toast, con
 * su cola y su sonido. No hay que montar nada en cada pantalla.
 *
 *   import { avisar, avisarError } from '@/composables/useAviso';
 *
 *   avisar('Imagen subida');                 // verde
 *   avisar('Revisá el archivo', 'warning');  // ámbar
 *   avisarError('No pude conectar con SIIGO');
 */

const TONOS = ['success', 'warning', 'info', 'error'];

export function avisar(mensaje, tono = 'success') {
    if (! mensaje) return;
    if (typeof window === 'undefined') return;

    const elegido = TONOS.includes(tono) ? tono : 'info';
    window.dispatchEvent(new CustomEvent('gb:aviso', {
        detail: { mensaje: String(mensaje), tono: elegido },
    }));
}

export function avisarError(mensaje) {
    avisar(mensaje || 'Hubo un error en la operación', 'error');
}

export function useAviso() {
    return { avisar, avisarError };
}
