<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * C-F1 · Desglose dual — Migración 7/6 · política del toggle enforced en BD.
 *
 * Un producto NO puede cambiar `desglose_stock` si ya tiene movimientos históricos
 * en `inventario_movimientos`. Cambiar el modo con movimientos existentes rompería
 * el kardex append-only (Q1 acumulado por variante, Q2 por producto → saldos no cuadran).
 *
 * Doble defensa: validación en Filament (Fase 3) + este trigger (última línea).
 *
 * Cambio permitido:  producto sin movimientos → toggle libre
 * Cambio bloqueado:  producto con movimientos → SIGNAL SQLSTATE '45000'
 */
return new class extends Migration {

    public function up(): void
    {
        // MariaDB no soporta CREATE OR REPLACE TRIGGER — hacemos drop + create.
        DB::unprepared("DROP TRIGGER IF EXISTS trg_bloqueo_toggle_desglose_stock");
        DB::unprepared("
            CREATE TRIGGER trg_bloqueo_toggle_desglose_stock
            BEFORE UPDATE ON productos
            FOR EACH ROW
            BEGIN
                IF NEW.desglose_stock <> OLD.desglose_stock THEN
                    IF EXISTS (SELECT 1 FROM inventario_movimientos WHERE producto_id = NEW.id LIMIT 1) THEN
                        SIGNAL SQLSTATE '45000'
                          SET MESSAGE_TEXT = 'No se puede cambiar desglose_stock: el producto ya tiene movimientos de kardex. Crea un producto nuevo con el modo deseado.';
                    END IF;
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_bloqueo_toggle_desglose_stock");
    }
};
