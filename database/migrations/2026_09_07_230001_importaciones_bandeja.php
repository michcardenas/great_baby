<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones_bandeja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('tipo', 30)->comment('facturas|contactos|productos|dropi|oc|pagos');
            $table->string('archivo_nombre', 200);
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('procesadas')->default(0);
            $table->unsignedInteger('ok')->default(0);
            $table->unsignedInteger('errores')->default(0);
            $table->string('estado', 20)->default('pendiente')->comment('pendiente|corriendo|terminado|fallido');
            $table->json('log')->nullable()->comment('array de líneas: [{fila,mensaje,tipo}]');
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('terminada_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones_bandeja');
    }
};
