<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PATRÓN α · el tipo del kardex tenía varchar(20) pero valores como
 *   `traslado_reversa_salida` (23) y `traslado_reversa_entrada` (25) los
 *   trunca. Ampliar a 32.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE inventario_movimientos MODIFY tipo VARCHAR(32) NOT NULL COMMENT 'ingreso|egreso|reserva|liberacion|traslado_*|ajuste|entrada_*'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inventario_movimientos MODIFY tipo VARCHAR(20) NOT NULL");
    }
};
