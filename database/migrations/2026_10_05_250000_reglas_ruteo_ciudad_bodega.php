<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOG-J4 · Ruteo automático pedido → bodega por ciudad del cliente.
 *
 * Don Jorge pierde tiempo decidiendo qué bodega despacha cada pedido B2B
 * según la ciudad del cliente (y cuando se olvida, pide algo desde Bogotá
 * para un cliente de Barranquilla y lo paga el flete). Esta tabla deja
 * configurable el ruteo:
 *   - una regla por ciudad (o grupo de ciudades)
 *   - una ubicación origen de despacho
 *   - prioridad por si una regla "catch-all" debe quedar al final
 *
 * Columna nueva en pedidos_cliente guarda la ubicación origen asignada
 * para que la Cola de alistamiento la filtre por sede.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_ruteo_ciudad', function (Blueprint $t) {
            $t->id();
            // "Bogotá", "Barranquilla", "*" (catch-all), etc. · case-insensitive al matchear.
            $t->string('ciudad', 100);
            $t->foreignId('ubicacion_id')->constrained('inventario_ubicaciones');
            // Prioridad baja (1) = se evalúa primero · "*" conviene con prioridad 999.
            $t->unsignedSmallInteger('prioridad')->default(100);
            $t->boolean('activa')->default(true);
            $t->text('notas')->nullable();
            $t->timestamps();

            $t->index(['activa', 'prioridad'], 'idx_reglas_ruteo_eval');
        });

        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->foreignId('ubicacion_origen_id')->nullable()->after('lista_precios_id')
                ->constrained('inventario_ubicaciones')->nullOnDelete();
            $t->index('ubicacion_origen_id', 'idx_pedidos_ubicacion_origen');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->dropIndex('idx_pedidos_ubicacion_origen');
            $t->dropConstrainedForeignId('ubicacion_origen_id');
        });
        Schema::dropIfExists('reglas_ruteo_ciudad');
    }
};
