<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes CRÍTICOS re-audit M4 Cartera:
 *
 *   Raíz B (DATOS C3) · tabla dedicada `facturas_consecutivos` para generar
 *     numeración transaccional por prefijo (SELECT ... FOR UPDATE atómico).
 *     Ancho dinámico — no rompe en 10 000+.
 *
 *   Raíz D (DATOS C2) · tabla `notas_credito` — entidad de primer nivel para
 *     cumplir con exigencia DIAN de libros locales consecutivos con CUFE.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Raíz B · consecutivos transaccionales.
        if (! Schema::hasTable('facturas_consecutivos')) {
            Schema::create('facturas_consecutivos', function (Blueprint $table) {
                $table->id();
                $table->string('prefijo', 40)->unique();
                $table->unsignedBigInteger('ultimo')->default(0);
                $table->timestamps();
            });
        }

        // Raíz D · notas crédito como entidad de primer nivel.
        if (! Schema::hasTable('notas_credito')) {
            Schema::create('notas_credito', function (Blueprint $table) {
                $table->id();
                $table->string('prefijo', 10)->default('NC');
                $table->unsignedBigInteger('numero');
                $table->foreignId('factura_id')->nullable()->constrained('facturas_venta')->nullOnDelete();
                $table->foreignId('devolucion_dropi_id')->nullable();
                $table->string('motivo', 200);
                $table->decimal('valor', 12, 2);
                $table->string('estado', 20)->default('emitida'); // emitida|enviada_siigo|aceptada_dian|rechazada|anulada
                $table->string('cufe', 100)->nullable();
                $table->string('siigo_id', 60)->nullable();
                $table->json('siigo_response')->nullable();
                $table->timestamp('emitida_at')->nullable();
                $table->timestamp('aceptada_dian_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['prefijo', 'numero'], 'notas_credito_prefijo_numero_unique');
                $table->index('factura_id');
                $table->index('devolucion_dropi_id');
                $table->index('estado');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_credito');
        Schema::dropIfExists('facturas_consecutivos');
    }
};
