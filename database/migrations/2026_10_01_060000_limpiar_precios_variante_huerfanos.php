<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FASE F · limpiar precios de variantes huérfanas en producción.
 *
 * La tabla `precios_variante` fue creada con `cascadeOnDelete()` sobre
 * `variante_id`, así que un DELETE normal del padre limpia sus precios.
 * Pero si en algún punto se hizo TRUNCATE con `SET FOREIGN_KEY_CHECKS=0`
 * (por ejemplo durante un reset masivo de pruebas), los CASCADE no se
 * ejecutan y quedan filas huérfanas.
 *
 * Síntoma visible: al crear nuevas variantes, los IDs reciclados heredan
 * precios ajenos que un dev anterior había cargado para variantes de
 * misma id (ahora borradas). En SIIGO se publican productos con precios
 * inesperados.
 *
 * Fix: borrar las filas de `precios_variante` cuyo `variante_id` ya no
 * existe en `producto_variantes`. Idempotente, seguro de correr en prod.
 */
return new class extends Migration {
    public function up(): void
    {
        $huerfanos = DB::table('precios_variante as pv')
            ->leftJoin('producto_variantes as v', 'v.id', '=', 'pv.variante_id')
            ->whereNull('v.id')
            ->pluck('pv.id');

        if ($huerfanos->isNotEmpty()) {
            DB::table('precios_variante')->whereIn('id', $huerfanos)->delete();
        }
    }

    public function down(): void
    {
        // Irreversible por diseño · estos datos ya no corresponden a
        // ninguna variante existente, no habría a quién devolvérselos.
    }
};
