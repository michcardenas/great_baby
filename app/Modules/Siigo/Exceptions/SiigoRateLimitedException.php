<?php

namespace App\Modules\Siigo\Exceptions;

/**
 * B3-M1 · Se lanza desde SiigoClient cuando SIIGO devuelve 429.
 * El Job la captura y ejecuta `$this->release($retryAfter)` — libera el slot
 * al worker SIN gastar un `try` y SIN dormir el proceso 30 s (que bloquea otros
 * jobs simultáneamente).
 *
 * `retryAfter` viene del header `Retry-After` de SIIGO o de un backoff
 * exponencial cuando no está presente.
 */
class SiigoRateLimitedException extends \RuntimeException
{
    public function __construct(public readonly int $retryAfter, string $path = '')
    {
        parent::__construct("SIIGO rate limit alcanzado en {$path} · retry en {$retryAfter}s");
    }
}
