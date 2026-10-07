<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `inventario_movimientos.referencia_tipo` nació como `varchar(40)` pensando en
 * códigos cortos (`inventario_fisico`, `dropi_pedido`, `pedido_b2b_despacho`),
 * pero cuatro Actions guardan ahí el nombre completo de la clase:
 *
 *   App\Modules\Compras\Models\RecepcionCompra   → 41 caracteres
 *   App\Modules\Inventario\Models\Traslado       → 38
 *   App\Modules\Inventario\Models\TomaFisica     → 40
 *   App\Modules\Compras\Models\Importacion       → 38
 *
 * La base de desarrollo tiene la columna en `varchar(191)` —se amplió a mano en
 * algún momento, fuera de las migraciones— así que acá todo funciona. En una
 * instalación nueva la columna mide 40 y recibir mercancía muere con
 * «Data too long for column 'referencia_tipo'» justo al escribir el kardex:
 * la recepción se revierte y no entra nada a bodega. Lo mismo traslados,
 * tomas físicas y liquidación de importaciones.
 *
 * Esta migración deja la columna como la base viva, para que desplegar desde
 * cero se comporte igual que lo que ya probamos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventario_movimientos', 'referencia_tipo')) {
            return;
        }

        if ($this->anchoActual() >= 191) {
            return;
        }

        // `change()` de Laravel necesita doctrine/dbal y además reescribiría el
        // índice; el ALTER directo es más predecible sobre una tabla con
        // triggers de inmutabilidad.
        DB::statement(
            'ALTER TABLE `inventario_movimientos`
             MODIFY `referencia_tipo` VARCHAR(191) NULL
             COMMENT "codigo corto (inventario_fisico, pedido_b2b_despacho) o FQCN del modelo de origen"'
        );
    }

    public function down(): void
    {
        // No se vuelve a 40: truncaría las referencias de recepciones y
        // traslados ya registradas y dejaría el kardex sin rastro de su origen.
    }

    private function anchoActual(): int
    {
        $col = DB::selectOne(
            'SELECT CHARACTER_MAXIMUM_LENGTH AS largo
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?',
            ['inventario_movimientos', 'referencia_tipo']
        );

        return (int) ($col->largo ?? 0);
    }
};
