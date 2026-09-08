<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envuelve una Action en try/catch + log estructurado.
 * Uso desde observers: SafeAction::run(SomeAction::class, $arg1, $arg2)
 * Nunca propaga excepciones al caller — el observer padre nunca revienta la tx padre.
 */
class SafeAction
{
    /**
     * @return mixed|null  Retorna el resultado del ::run o null si hubo error.
     */
    public static function run(string $actionClass, mixed ...$args): mixed
    {
        try {
            return $actionClass::run(...$args);
        } catch (Throwable $e) {
            Log::error("[SafeAction] {$actionClass} falló", [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'args_preview' => self::previewArgs($args),
            ]);
            // Re-lanzar excepciones de BD para NO romper la tx padre silenciosamente.
            // Fallos de negocio (RuntimeException, LogicException) sí se tragan.
            if ($e instanceof \PDOException
                || $e instanceof \Illuminate\Database\QueryException
                || $e instanceof \Illuminate\Database\DeadlockException) {
                throw $e;
            }
            return null;
        }
    }

    private static function previewArgs(array $args): array
    {
        return array_map(function ($a) {
            if (is_object($a)) return get_class($a) . '#' . ($a->id ?? '?');
            if (is_scalar($a)) return $a;
            return gettype($a);
        }, $args);
    }
}
