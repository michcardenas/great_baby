/**
 * Convierte una tabla en una lista de tarjetas cuando la pantalla es angosta.
 *
 * En el celular una tabla de diez columnas es inservible: hay que arrastrar de
 * lado y, al hacerlo, se pierde la columna que identifica la fila. En Productos
 * quedaban seis filas idénticas —misma línea, mismo precio, misma fecha— sin
 * forma de saber qué producto era cada una.
 *
 * En vez de reescribir a mano las 124 tablas del ERP, acá se copia el texto de
 * cada encabezado dentro de su celda (`data-label`) y el CSS de app.css se
 * encarga de apilar debajo de 768px. De ahí para arriba no cambia nada.
 *
 * Es defensiva a propósito:
 *
 *   - Si una fila no tiene tantas celdas como encabezados —el caso típico es
 *     la fila de «sin resultados», que usa un colspan— se marca aparte y se
 *     muestra entera, sin etiqueta.
 *   - Si la tabla entera no se puede etiquetar bien, no se apila: queda como
 *     está hoy, con desplazamiento lateral. Nunca deja la pantalla peor de lo
 *     que estaba.
 *
 * Uso:
 *
 *   <table v-tabla-movil> … </table>
 */

function etiquetar(tabla) {
    // La última fila del thead es la que tiene los nombres de columna cuando
    // hay encabezado en dos pisos.
    const encabezados = [...tabla.querySelectorAll('thead tr:last-child th')];

    if (encabezados.length < 2 || encabezados.some((th) => th.colSpan > 1)) {
        tabla.removeAttribute('data-apilable');
        return;
    }

    const textos = encabezados.map((th) => th.textContent.trim());

    for (const fila of tabla.querySelectorAll('tbody tr')) {
        const celdas = [...fila.children].filter((c) => c.tagName === 'TD');
        const encaja = celdas.length === textos.length && celdas.every((td) => td.colSpan === 1);

        if (! encaja) {
            fila.setAttribute('data-sin-etiquetas', '');
            continue;
        }

        fila.removeAttribute('data-sin-etiquetas');
        celdas.forEach((td, i) => {
            // Solo se escribe si cambió: `updated` se dispara en cada filtrado
            // y en tablas largas no vale la pena repetir cientos de escrituras.
            if (td.getAttribute('data-label') !== textos[i]) {
                td.setAttribute('data-label', textos[i]);
            }
        });
    }

    tabla.setAttribute('data-apilable', 'si');
}

/**
 * Avisa cuando la tabla quedó sin filas.
 *
 * Una tabla con encabezado y nada debajo parece rota: no se sabe si el filtro
 * no encontró nada, si todavía está cargando o si el sistema falló. 49 tablas
 * del ERP se veían así. En vez de escribir una fila de «sin resultados» en cada
 * una, se marca el contenedor y el CSS pinta el mensaje.
 *
 * Se escribe en el contenedor y no en la propia tabla porque un `::after` sobre
 * un `<table>` cae dentro del flujo de tablas y el navegador lo acomoda donde
 * quiere; el contenedor es un div normal y se comporta.
 *
 * Las tablas que ya traen su propia fila de «sin resultados» no entran acá: su
 * `tbody` no está vacío, así que no se pisan ni sale el mensaje dos veces.
 *
 * Para darle un texto propio a una tabla:  <table v-tabla-movil data-vacia="…">
 */
const MENSAJE_POR_DEFECTO = 'No hay nada que mostrar con estos filtros.';

function marcarVacia(tabla) {
    const contenedor = tabla.parentElement;
    if (! contenedor) return;

    const hayFilas = tabla.querySelectorAll('tbody tr').length > 0;

    if (hayFilas) {
        contenedor.removeAttribute('data-tabla-vacia');
        return;
    }

    contenedor.setAttribute('data-tabla-vacia', tabla.dataset.vacia || MENSAJE_POR_DEFECTO);
}

export default {
    mounted(el) {
        etiquetar(el);
        marcarVacia(el);
    },
    updated(el) {
        etiquetar(el);
        marcarVacia(el);
    },
};
