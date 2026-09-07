<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §9 Diseño Dropi — Código de barras propio por variante:
 * [Referencia] - [ColorCodigo][DiseñoCodigo] - [Talla]
 * Ej: AND2512-79/154-02LEÓ-6M
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('color_codigo', 8)->nullable()->comment('Ej: 02');
            $table->string('color_nombre', 30)->nullable()->comment('Ej: azul');
            $table->string('diseno_codigo', 8)->nullable()->comment('Ej: LEÓ');
            $table->string('diseno_nombre', 30)->nullable()->comment('Ej: león');
            $table->string('talla', 8)->nullable()->comment('6M | 9M | 12M | 18M | 24M | null');
            $table->string('codigo_barras', 60)->unique()->comment('Generado: [ref]-[color+diseño]-[talla]');
            $table->timestamps();

            $table->index(['producto_id', 'talla']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_variantes');
    }
};
