<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de cobranza — cada intento de contacto queda registrado.
 * Fuente: whatsapp | email | llamada | manual.
 * Escalamiento automático según tramo de antigüedad §7 TO-BE Cartera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranzas_registro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas_venta');
            $table->foreignId('contacto_id')->constrained('contactos');
            $table->string('canal', 20)->comment('whatsapp|email|llamada|manual');
            $table->string('tramo', 20)->comment('0-30|31-59|60-89|90-119|120+');
            $table->string('estado', 20)->default('enviado')
                ->comment('enviado|leido|respondido|escalado|resuelto|fallido');
            $table->text('mensaje')->nullable();
            $table->json('respuesta_api')->nullable();
            $table->foreignId('gestor_id')->nullable()->constrained('users');
            $table->timestamp('enviado_at');
            $table->timestamps();

            $table->index(['factura_id', 'enviado_at']);
            $table->index(['contacto_id', 'tramo']);
        });
    }

    public function down(): void { Schema::dropIfExists('cobranzas_registro'); }
};
