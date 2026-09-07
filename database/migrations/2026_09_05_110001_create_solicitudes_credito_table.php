<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §7 TO-BE Cartera — Workflow de excepciones.
 * Cuando el motor retiene un pedido, se crea una solicitud que Cartera puede aprobar
 * o escalar a Gerencia según el monto/riesgo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contacto_id')->constrained('contactos');
            $table->decimal('monto_pedido', 14, 2);
            $table->text('motivo_retencion');
            $table->json('snapshot_credito');
            $table->string('estado', 20)->default('pendiente')
                ->comment('pendiente | aprobada_cartera | aprobada_gerencia | rechazada');
            $table->string('nivel_actual', 20)->default('cartera')->comment('cartera | gerencia');
            $table->foreignId('solicitada_por')->nullable()->constrained('users');
            $table->foreignId('resuelta_por')->nullable()->constrained('users');
            $table->text('resolucion_notas')->nullable();
            $table->timestamp('resuelta_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'nivel_actual']);
        });
    }

    public function down(): void { Schema::dropIfExists('solicitudes_credito'); }
};
