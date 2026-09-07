<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_importaciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique()->comment('IMP-2026-000001');
            $table->string('contenedor', 30)->nullable();
            $table->string('bl_awb', 60)->nullable();

            $table->string('proveedor_pais', 60)->nullable();
            $table->string('puerto_origen', 60)->nullable();
            $table->string('puerto_destino', 60)->nullable();
            $table->string('incoterm', 10)->nullable()->comment('FOB, CIF, EXW...');

            $table->string('moneda_origen', 3)->default('USD');
            $table->decimal('tasa_cambio_liquidacion', 14, 6)->nullable();

            $table->date('fecha_zarpe')->nullable();
            $table->date('eta')->nullable();
            $table->date('fecha_llegada')->nullable();
            $table->date('fecha_nacionalizacion')->nullable();
            $table->date('fecha_liquidacion')->nullable();

            $table->decimal('valor_fob', 14, 2)->default(0);
            $table->decimal('valor_gastos', 14, 2)->default(0);
            $table->decimal('valor_arancel', 14, 2)->default(0);
            $table->decimal('valor_iva_importacion', 14, 2)->default(0);
            $table->decimal('valor_total_costo', 14, 2)->default(0);

            $table->string('estado', 30)->default('en_transito')->index()
                ->comment('en_transito | en_puerto | nacionalizada | liquidada | cerrada');

            $table->text('observaciones')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users');
            $table->foreignId('liquidada_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('compras_importacion_ordenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_id')->constrained('compras_importaciones')->cascadeOnDelete();
            $table->foreignId('orden_id')->constrained('compras_ordenes');
            $table->decimal('peso_kg', 12, 3)->nullable()->comment('Base para prorrateo por peso');
            $table->decimal('volumen_m3', 12, 4)->nullable();
            $table->timestamps();

            $table->unique(['importacion_id', 'orden_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_importacion_ordenes');
        Schema::dropIfExists('compras_importaciones');
    }
};
