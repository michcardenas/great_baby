<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_ordenes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique()->comment('OC-2026-000001');
            $table->foreignId('proveedor_id')->constrained('contactos');
            $table->foreignId('bodega_id')->nullable()->constrained('inventario_ubicaciones');
            $table->foreignId('condicion_credito_id')->nullable()->constrained('condiciones_credito');

            $table->string('tipo', 20)->default('nacional')->comment('nacional | importacion');
            $table->string('moneda', 3)->default('COP');
            $table->decimal('tasa_cambio', 14, 6)->default(1);

            $table->date('fecha_emision');
            $table->date('fecha_esperada')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('iva', 14, 2)->default(0);
            $table->decimal('reteica', 14, 2)->default(0);
            $table->decimal('retefuente', 14, 2)->default(0);
            $table->decimal('reteiva', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->string('estado', 30)->default('borrador')->index()
                ->comment('borrador | enviada | aprobada | parcial | recibida | cerrada | anulada');

            $table->foreignId('creado_por')->nullable()->constrained('users');
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->timestamp('aprobado_at')->nullable();

            $table->text('observaciones')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'proveedor_id']);
            $table->index('fecha_emision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_ordenes');
    }
};
