<?php

namespace App\Modules\Siigo\Exceptions;

use RuntimeException;

/**
 * La credencial de SIIGO no sirve: vencida, revocada o mal escrita.
 *
 * Es distinto de «SIIGO rechazó este documento». Esto no es culpa del
 * documento que se estaba mandando: mientras la llave esté muerta **todo** va
 * a fallar igual, así que no tiene sentido que cada job gaste sus reintentos
 * contra una pared.
 *
 * Y eso fue literal: la llave venció el 2026-10-08 y, como cada push quemaba
 * sus 5 intentos, quedaron 1008 trabajos en `failed_jobs` con
 * `MaxAttemptsExceeded` — un mensaje que además **tapa la causa real**, porque
 * sustituye al error original. 452 productos, o sea el catálogo entero, y por
 * fuera parecían 452 problemas distintos.
 *
 * Hereda de RuntimeException para no romper los `catch (RuntimeException)` que
 * ya existen; lo que agrega es poder distinguirla.
 */
class CredencialSiigoInvalida extends RuntimeException
{
}
