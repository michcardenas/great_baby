<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 · B.4 · Asientos contables manuales.
 * Aracely arma un asiento (traslado entre cuentas, ajuste fin de mes) con N
 * líneas débito/crédito que deben cuadrar (partida doble). Sistema:
 *  1) valida debe=haber
 *  2) crea movimientos_contables planos (para nuestros reportes)
 *  3) empuja el asiento agrupado a SIIGO /v1/journals
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asientos_manuales', function (Blueprint $t) {
            $t->id();
            $t->date('fecha');
            $t->string('glosa', 500);
            $t->decimal('valor_total', 15, 2)->default(0);
            $t->enum('estado', ['borrador', 'cuadrado', 'aprobado', 'sincronizado'])->default('borrador');
            $t->string('siigo_journal_id', 64)->nullable()->unique();
            $t->timestamp('siigo_sync_at')->nullable();
            $t->foreignId('user_id')->constrained('users');
            $t->timestamps();
            $t->softDeletes();
            $t->index('fecha');
            $t->index('estado');
        });

        Schema::create('asiento_manual_lineas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asiento_manual_id')->constrained('asientos_manuales')->cascadeOnDelete();
            $t->string('cuenta_puc', 20);
            $t->string('tercero_documento', 30)->nullable();
            $t->decimal('debe', 15, 2)->default(0);
            $t->decimal('haber', 15, 2)->default(0);
            $t->string('descripcion', 300);
            $t->string('centro_costo', 20)->nullable();
            $t->unsignedSmallInteger('orden')->default(0);
            $t->timestamps();
            $t->index(['asiento_manual_id', 'orden']);
            $t->index('cuenta_puc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asiento_manual_lineas');
        Schema::dropIfExists('asientos_manuales');
    }
};
