<?php

use App\Support\Reglas;

if (! function_exists('setting')) {
    /**
     * Lee una regla de negocio configurable por Aracely.
     * Uso: setting('empaque.max_foto_bytes', 3_000_000)
     */
    function setting(string $clave, mixed $default = null): mixed
    {
        return Reglas::get($clave, $default);
    }
}
