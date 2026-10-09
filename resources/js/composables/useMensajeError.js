/**
 * Convierte el error de una petición en algo que una persona pueda leer.
 *
 * El patrón que estaba repartido por el ERP terminaba casi siempre en
 * `|| e.message`, y eso es lo que terminaba en pantalla: «Network Error»,
 * «Request failed with status code 500», «timeout of 0ms exceeded». Aracely no
 * puede hacer nada con eso y tampoco sabe si su documento se guardó o no.
 *
 * Acá se busca primero lo que haya dicho el servidor, que suele ser lo más
 * preciso; si no dijo nada se traduce el código HTTP a una frase que indica
 * qué hacer. El detalle técnico no se pierde: se manda a la consola.
 *
 *   import { mensajeDeError } from '@/composables/useMensajeError';
 *   catch (e) { avisarError(mensajeDeError(e, 'No pude guardar el producto')); }
 */

const POR_ESTADO = {
    401: 'Se cerró la sesión. Volvé a entrar.',
    403: 'No tenés permiso para hacer esto.',
    404: 'Eso ya no existe. Puede que alguien lo haya borrado.',
    409: 'Alguien más cambió este registro. Recargá y volvé a intentar.',
    413: 'El archivo pesa demasiado.',
    419: 'La página estuvo abierta mucho rato. Recargá y volvé a intentar.',
    422: 'Hay datos que no pasan la validación.',
    429: 'Demasiados intentos seguidos. Esperá un momento.',
};

export function mensajeDeError(e, respaldo = 'Hubo un error en la operación') {
    // El detalle crudo se guarda para quien revise la consola, no para pantalla.
    if (typeof console !== 'undefined') console.error('[error]', e);

    const datos = e?.response?.data;

    // 1. Lo que haya dicho el servidor a propósito.
    if (datos?.mensaje) return String(datos.mensaje);
    if (datos?.message && ! /^(Server Error|Internal Server Error)$/i.test(datos.message)) {
        return String(datos.message);
    }

    // 2. Errores de validación: se listan los campos, que es lo accionable.
    if (datos?.errors && typeof datos.errors === 'object') {
        const lista = Object.values(datos.errors).flat().filter(Boolean);
        if (lista.length) return lista.join(' · ');
    }

    // 3. Por código HTTP.
    const estado = e?.response?.status;
    if (estado && POR_ESTADO[estado]) return POR_ESTADO[estado];
    if (estado >= 500) return 'El servidor falló al procesar esto. Si se repite, avisá a soporte.';

    // 4. Sin respuesta: se cayó la red o el servidor no contestó.
    if (e?.request && ! e?.response) return 'Sin conexión con el servidor. Revisá internet y reintentá.';

    return respaldo;
}

export function useMensajeError() {
    return { mensajeDeError };
}
