<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_importacion_gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_id')->constrained('compras_importaciones')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('contactos');

            $table->string('concepto', 30)->comment('flete | seguro | arancel | iva_importacion | agente_aduana | almacenaje | transporte_interno | otros');
            $table->string('descripcion', 255);

            $table->string('moneda', 3)->default('COP');
            $table->decimal('monto', 14, 2);
            $table->decimal('monto_base', 14, 2)->comment('Monto en COP para prorrateo');

            $table->boolean('capitalizable')->default(true)
                ->comment('Si true, entra al costo del producto; si false, va a gasto (5195)');
            $table->string('metodo_prorrateo', 20)->default('valor')
                ->comment('valor | cantidad | peso | volumen | manual');

            $table->string('factura_proveedor', 60)->nullable();
            $table->date('fecha')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_importacion_gastos');
    }
};
