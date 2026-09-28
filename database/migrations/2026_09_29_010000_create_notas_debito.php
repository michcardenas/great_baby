<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 · B.2 · Tabla notas_debito.
 * Espejo de notas_credito · sin devolucion_dropi_id (débito no viene de Dropi).
 * Causal típica: recargo por mora, ajuste al alza, intereses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_debito', function (Blueprint $t) {
            $t->id();
            $t->string('prefijo', 8)->default('ND');
            $t->unsignedInteger('numero');
            $t->foreignId('factura_id')->constrained('facturas_venta');
            $t->string('motivo', 500);
            $t->decimal('valor', 15, 2);
            $t->enum('estado', ['borrador', 'emitida', 'aceptada_dian', 'rechazada'])->default('borrador');
            $t->string('cufe', 200)->nullable();
            $t->string('siigo_id', 64)->nullable()->unique();
            $t->json('siigo_response')->nullable();
            $t->timestamp('emitida_at')->nullable();
            $t->timestamp('aceptada_dian_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['prefijo', 'numero']);
            $t->index('factura_id');
            $t->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_debito');
    }
};
