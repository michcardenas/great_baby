<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1-A · Bitácora de interacciones CRM con cada contacto.
 * Timeline vertical en la ficha del contacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_interacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contacto_id')->constrained('contactos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('tipo', 20)->comment('llamada|correo|whatsapp|visita|nota|reunion|reclamo');
            $table->string('asunto', 200);
            $table->text('detalle')->nullable();
            $table->string('resultado', 20)->nullable()->comment('exitoso|sin_respuesta|reagendar|cerrado');
            $table->timestamp('ocurrida_at');
            $table->timestamp('proxima_accion_at')->nullable()
                ->comment('Fecha del próximo contacto sugerido');
            $table->text('proxima_accion_nota')->nullable();
            $table->json('adjuntos')->nullable();
            $table->timestamps();

            $table->index(['contacto_id', 'ocurrida_at']);
            $table->index(['user_id', 'ocurrida_at']);
            $table->index(['tipo', 'ocurrida_at']);
            $table->index('proxima_accion_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_interacciones');
    }
};
