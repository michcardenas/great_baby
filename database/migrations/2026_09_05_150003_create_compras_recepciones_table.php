<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_recepciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique()->comment('REC-2026-000001');
            $table->foreignId('orden_id')->constrained('compras_ordenes');
            $table->foreignId('bodega_id')->constrained('inventario_ubicaciones');
            $table->foreignId('recibido_por')->nullable()->constrained('users');

            $table->date('fecha_recepcion');
            $table->string('remision_proveedor', 60)->nullable();
            $table->string('factura_proveedor', 60)->nullable();
            $table->string('transportista', 120)->nullable();

            $table->string('estado', 20)->default('borrador')->index()
                ->comment('borrador | confirmada | anulada');
            $table->timestamp('confirmada_at')->nullable();

            $table->decimal('total_recibido', 14, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_recepciones');
    }
};
