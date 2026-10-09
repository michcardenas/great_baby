<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vuelve a crear los CHECK del «desglose dual»: cada fila tiene que apuntar a
 * una variante O a un producto, nunca a ninguno de los dos.
 *
 * Las migraciones del 2026-09-21 los BORRAN («limpieza de una corrida previa
 * fallida») y nunca los vuelven a crear. En la base de desarrollo existen
 * porque se agregaron a mano en su momento; en una instalación nueva no, y sin
 * ellos la base acepta movimientos de kardex sin sujeto, ítems de factura sin
 * producto y reservas colgando de nada. Eso corrompe el inventario en silencio
 * y después no hay forma de saber a qué correspondía cada fila.
 *
 * Verificado comparando `information_schema.TABLE_CONSTRAINTS` entre la base
 * viva y una levantada desde cero: faltaban las ocho.
 */
return new class extends Migration
{
    /** tabla => nombre del check */
    private const CHECKS = [
        'inventario_movimientos' => 'chk_invmov_sujeto',
        'alertas_stock_config' => 'chk_alerta_config_sujeto',
        'alertas_stock_disparadas' => 'chk_alerta_disp_sujeto',
        'factura_venta_items' => 'chk_factura_venta_item_sujeto',
        'pedidos_cliente_items' => 'chk_pedido_cliente_item_sujeto',
        'reservas_inventario' => 'chk_reserva_sujeto',
        'tomas_fisicas_items' => 'chk_toma_item_sujeto',
        'traslados_inventario_items' => 'chk_traslado_item_sujeto',
    ];

    public function up(): void
    {
        foreach (self::CHECKS as $tabla => $check) {
            if (! Schema::hasTable($tabla)
                || ! Schema::hasColumn($tabla, 'variante_id')
                || ! Schema::hasColumn($tabla, 'producto_id')) {
                continue;
            }

            if ($this->existe($tabla, $check)) {
                continue;
            }

            // Si quedaron filas sin sujeto de antes, el ALTER falla y deja la
            // migración a medias. Se avisa con el dato concreto en vez de
            // dejar que reviente con un error de MySQL.
            $huerfanas = DB::table($tabla)->whereNull('variante_id')->whereNull('producto_id')->count();
            if ($huerfanas > 0) {
                throw new RuntimeException(
                    "No se puede proteger «{$tabla}»: hay {$huerfanas} fila(s) sin variante ni producto. "
                    .'Hay que corregirlas antes de aplicar la restricción.'
                );
            }

            $this->quitarCascadaEnUpdate($tabla);

            DB::statement(
                "ALTER TABLE `{$tabla}` ADD CONSTRAINT `{$check}` "
                .'CHECK (`variante_id` IS NOT NULL OR `producto_id` IS NOT NULL)'
            );
        }
    }

    public function down(): void
    {
        foreach (self::CHECKS as $tabla => $check) {
            if (Schema::hasTable($tabla) && $this->existe($tabla, $check)) {
                // DROP CONSTRAINT y no DROP CHECK: el segundo es sintaxis de
                // MySQL y MariaDB lo rechaza con error 1064.
                DB::statement("ALTER TABLE `{$tabla}` DROP CONSTRAINT `{$check}`");
            }
        }
    }

    /**
     * MariaDB no acepta un CHECK sobre una columna que sea hija de una llave
     * foránea con ON UPDATE CASCADE: devuelve el error 1901 «Function or
     * expression 'producto_id' cannot be used in the CHECK clause». Las ocho
     * FK de `producto_id` se crearon con onUpdate('cascade'), que no aporta
     * nada porque `productos.id` es un autoincremental cuyo valor nunca
     * cambia; no hay actualización que propagar. Se vuelve a crear la FK con
     * la regla por defecto en UPDATE (RESTRICT) y se conserva tal cual la de
     * DELETE, que sí se usa.
     */
    private function quitarCascadaEnUpdate(string $tabla): void
    {
        $fks = DB::select(
            'SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME,
                    k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
               FROM information_schema.KEY_COLUMN_USAGE k
               JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                 ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
              WHERE k.CONSTRAINT_SCHEMA = DATABASE()
                AND k.TABLE_NAME = ?
                AND k.COLUMN_NAME IN (\'variante_id\', \'producto_id\')
                AND r.UPDATE_RULE = \'CASCADE\'',
            [$tabla]
        );

        foreach ($fks as $fk) {
            $onDelete = in_array($fk->DELETE_RULE, ['CASCADE', 'SET NULL'], true)
                ? " ON DELETE {$fk->DELETE_RULE}"
                : '';

            DB::statement("ALTER TABLE `{$tabla}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            DB::statement(
                "ALTER TABLE `{$tabla}` ADD CONSTRAINT `{$fk->CONSTRAINT_NAME}` "
                ."FOREIGN KEY (`{$fk->COLUMN_NAME}`) "
                ."REFERENCES `{$fk->REFERENCED_TABLE_NAME}` (`{$fk->REFERENCED_COLUMN_NAME}`)"
                .$onDelete
            );
        }
    }

    private function existe(string $tabla, string $check): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $tabla)
            ->where('CONSTRAINT_NAME', $check)
            ->exists();
    }
};
