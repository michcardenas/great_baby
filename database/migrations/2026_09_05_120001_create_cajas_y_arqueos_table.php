<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TO-BE Contabilidad P1 — Cajas físicas y arqueo diario.
 * Cada caja tiene un responsable y una cuenta contable.
 * Los arqueos comparan saldo físico contado vs saldo esperado en libros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 100);
            $table->foreignId('responsable_id')->nullable()->constrained('users');
            $table->string('cuenta_puc', 20)->default('1105');
            $table->decimal('saldo_inicial', 14, 2)->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('arqueos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas');
            $table->date('fecha');
            $table->decimal('saldo_esperado', 14, 2);
            $table->decimal('saldo_contado', 14, 2);
            $table->decimal('diferencia', 14, 2);
            $table->string('resultado', 20)->comment('exacto|sobrante|faltante');
            $table->foreignId('realizado_por')->constrained('users');
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['caja_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqueos');
        Schema::dropIfExists('cajas');
    }
};
