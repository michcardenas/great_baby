<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1-C · Motor de comisiones para vendedores.
 * - `comisiones_config`: % base + escalones opcionales por vendedor.
 * - `comisiones_calculadas`: cierre mensual por vendedor con detalle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comisiones_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('porcentaje_base', 5, 2)->default(3.00)
                ->comment('% sobre venta cobrada');
            $table->boolean('cobra_solo_cobrado')->default(true)
                ->comment('true = comisiona solo lo pagado; false = sobre facturado');
            $table->decimal('meta_mensual', 14, 2)->nullable()
                ->comment('Meta de venta en $ para bono adicional');
            $table->decimal('bono_por_meta_pct', 5, 2)->nullable()
                ->comment('% adicional si cumple meta (ej: +1%)');
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique('vendedor_id');
        });

        Schema::create('comisiones_calculadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('users');
            $table->unsignedSmallInteger('anio');   // 4 dígitos (2026, 2027…)
            $table->unsignedTinyInteger('mes');     // 1-12
            $table->decimal('total_facturado', 14, 2)->default(0);
            $table->decimal('total_cobrado', 14, 2)->default(0);
            $table->decimal('base_comisionable', 14, 2)->default(0);
            $table->decimal('porcentaje_aplicado', 5, 2)->default(0);
            $table->decimal('comision', 14, 2)->default(0);
            $table->decimal('bono_meta', 14, 2)->default(0);
            $table->decimal('total_a_pagar', 14, 2)->default(0);
            $table->json('detalle_facturas')->nullable()
                ->comment('array de {factura_id, numero, total, cobrado_en_mes}');
            $table->string('estado', 20)->default('borrador')
                ->comment('borrador|aprobado|pagado');
            $table->timestamp('calculada_at');
            $table->timestamp('aprobada_at')->nullable();
            $table->timestamp('pagada_at')->nullable();
            $table->foreignId('aprobada_por')->nullable()->constrained('users');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['vendedor_id', 'anio', 'mes']);
            $table->index(['anio', 'mes']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones_calculadas');
        Schema::dropIfExists('comisiones_config');
    }
};
