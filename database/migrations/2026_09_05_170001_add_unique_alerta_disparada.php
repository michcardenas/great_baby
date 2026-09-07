<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix auditor #10: sin unique parcial, si dos workers ejecutan VerificarAlertasStock
 * en paralelo pueden crear la misma alerta 2 veces.
 * SQLite/MySQL 8+ soporta unique parcial vía índice funcional (aquí simulado con columna).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas_stock_disparadas', function (Blueprint $table) {
            $table->unique(['config_id', 'tipo', 'resuelta'], 'idx_alerta_unica_abierta');
        });
    }

    public function down(): void
    {
        Schema::table('alertas_stock_disparadas', function (Blueprint $table) {
            $table->dropUnique('idx_alerta_unica_abierta');
        });
    }
};
