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

if (! function_exists('feature')) {
    /**
     * Lee un feature flag de config/features.php.
     * Uso: feature('desglose_dual') → bool
     * Apagar en prod: FEATURE_DESGLOSE_DUAL=false en .env + config:cache.
     */
    function feature(string $flag): bool
    {
        return (bool) config("features.{$flag}", false);
    }
}
