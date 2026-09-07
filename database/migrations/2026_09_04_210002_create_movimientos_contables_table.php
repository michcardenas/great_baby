<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libro diario genérico — cada operación (venta, compra, devolución, garantía, sanción, retiro wallet)
 * escribe aquí una línea polimórfica. Cuando llegue Contabilidad, ya tiene los datos.
 *
 * Origen puede ser: dropi_pedido, dropi_devolucion, dropi_sancion, dropi_wallet_movimiento,
 * garantia_ticket, compra_orden (futuro), factura_venta (futuro), etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_contables', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->string('cuenta_puc', 20)->index()->comment('Ej: 1105, 4135, 2408, 1355');
            $table->string('tercero_type', 100)->nullable()->comment('App\Models\Contacto | App\Models\User | dropi | anonimo');
            $table->unsignedBigInteger('tercero_id')->nullable();
            $table->decimal('debe', 14, 2)->default(0);
            $table->decimal('haber', 14, 2)->default(0);

            // Polimorfismo: apunta al modelo que originó el movimiento
            $table->string('origen_type', 100)->comment('App\\Modules\\Dropi\\Models\\DropiPedido, etc.');
            $table->unsignedBigInteger('origen_id');

            $table->string('descripcion', 255)->nullable();
            $table->string('centro_costo', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['origen_type', 'origen_id']);
            $table->index(['tercero_type', 'tercero_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_contables');
    }
};
