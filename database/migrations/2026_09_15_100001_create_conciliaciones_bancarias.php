<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conciliación bancaria diaria (dominio de contabilidad / Silvia).
 * Cada registro es la conciliación de una cuenta bancaria en una fecha; se envía a
 * SIIGO como asiento en modo BORRADOR para que contabilidad ajuste los valores allá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliaciones_bancarias', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->string('banco', 120);
            $table->string('cuenta_puc', 20)->default('1110')->comment('Cuenta PUC del banco, ej. 1110');
            $table->decimal('saldo_extracto', 16, 2)->default(0);
            $table->decimal('saldo_sistema', 16, 2)->default(0);
            $table->decimal('diferencia', 16, 2)->default(0)->comment('saldo_extracto - saldo_sistema');
            $table->string('estado', 20)->default('pendiente')->comment('pendiente | conciliada | exportada');
            $table->text('notas')->nullable();

            // Rastro de exportación a SIIGO (lo llena SiigoExportService::persistirReferencia).
            $table->string('siigo_id')->nullable();
            $table->boolean('siigo_borrador')->default(false);
            $table->timestamp('siigo_exportado_at')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliaciones_bancarias');
    }
};
