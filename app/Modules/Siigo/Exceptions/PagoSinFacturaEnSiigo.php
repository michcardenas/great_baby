<?php

namespace App\Modules\Siigo\Exceptions;

use RuntimeException;

/**
 * El pago cuelga de una factura que **nunca** va a estar en SIIGO.
 *
 * No es un problema de orden ni de esperar: SIIGO sólo recibe las facturas
 * electrónicas de la DIAN, y la red de seguridad diaria filtra por
 * `es_electronica = 1`. Un recibo contra una factura que no es electrónica no
 * tiene a qué aplicarse allá, ni hoy ni dentro de un mes.
 *
 * Antes esto se lanzaba como un `RuntimeException` común: el job lo trataba
 * como fallo, gastaba intentos y terminaba en `failed_jobs`, y al día
 * siguiente `siigo:empujar-pendientes` lo volvía a encolar para que muriera
 * igual. Medido el 2026-10-09: las 23 facturas sin `siigo_id` eran todas no
 * electrónicas (demo y semilla) y 3 pagos llevaban días en ese bucle.
 *
 * Distinguirla permite registrarlo como **omitido** y seguir, que es lo que
 * de verdad pasó.
 */
class PagoSinFacturaEnSiigo extends RuntimeException
{
}
