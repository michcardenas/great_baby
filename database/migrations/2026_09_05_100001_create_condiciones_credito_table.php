<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Condiciones de crédito B2B por contacto (§7 TO-BE Cartera).
 * Un contacto puede tener varias condiciones históricas — la vigente es la más reciente activa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condiciones_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contacto_id')->constrained('contactos')->cascadeOnDelete();
            $table->decimal('cupo', 14, 2)->default(0)->comment('Cupo máximo en pesos');
            $table->unsignedSmallInteger('plazo_dias')->default(30)->comment('Días de plazo para pago');
            $table->decimal('descuento_pronto_pago_pct', 5, 2)->default(0)->comment('Ej: 10.00 = 10%');
            $table->unsignedSmallInteger('plazo_pronto_pago_dias')->default(10);
            $table->boolean('flete_asumido_gb')->default(false);
            $table->boolean('activa')->default(true);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->foreignId('aprobada_por')->nullable()->constrained('users');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['contacto_id', 'activa']);
        });
    }

    public function down(): void { Schema::dropIfExists('condiciones_credito'); }
};
