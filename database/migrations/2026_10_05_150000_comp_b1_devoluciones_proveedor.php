<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COMP-B1 · Devolución a proveedor + Nota Crédito compra SIIGO.
 *
 * Hoy el ERP no tiene flujo de devolución a proveedor: si llega mercancía
 * defectuosa, no hay forma documental de reversar la factura ni ajustar CxP
 * ni dar baja al stock con trazabilidad. Esta migración crea:
 *   - devoluciones_proveedor (header)
 *   - devoluciones_proveedor_items (líneas)
 * El Action RegistrarDevolucionProveedor orquesta kardex + asiento local
 * + push a SIIGO como NC de compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('devoluciones_proveedor')) {
            Schema::create('devoluciones_proveedor', function (Blueprint $t) {
                $t->id();
                $t->string('numero', 30)->unique();
                $t->foreignId('proveedor_id')->constrained('contactos')->restrictOnDelete();
                // La tabla se llama `compras_recepciones`; con el nombre anterior
                // MySQL abortaba con errno 150 y la instalación limpia no pasaba
                // de acá (en la base ya existente no se notaba).
                $t->foreignId('recepcion_id')->nullable()->constrained('compras_recepciones')->nullOnDelete();
                $t->foreignId('ubicacion_id')->constrained('inventario_ubicaciones')->restrictOnDelete();
                $t->date('fecha');
                $t->string('motivo', 500);
                $t->enum('estado', ['borrador', 'confirmada', 'anulada'])->default('borrador');
                $t->decimal('subtotal', 14, 2)->default(0);
                $t->decimal('iva', 14, 2)->default(0);
                $t->decimal('total', 14, 2)->default(0);
                // Idempotencia SIIGO · si NC ya creada no se duplica.
                $t->string('siigo_id', 64)->nullable()->index();
                $t->timestamp('siigo_sync_at')->nullable();
                $t->foreignId('creada_por')->constrained('users')->restrictOnDelete();
                $t->timestamp('confirmada_at')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('devoluciones_proveedor_items')) {
            Schema::create('devoluciones_proveedor_items', function (Blueprint $t) {
                $t->id();
                $t->foreignId('devolucion_id')->constrained('devoluciones_proveedor')->cascadeOnDelete();
                // Polimórfico igual que el resto del catálogo (producto XOR variante).
                $t->foreignId('variante_id')->nullable()->constrained('producto_variantes')->restrictOnDelete();
                $t->foreignId('producto_id')->nullable()->constrained('productos')->restrictOnDelete();
                $t->decimal('cantidad', 12, 4);
                $t->decimal('costo_unit', 14, 4);
                $t->decimal('iva_pct', 5, 2)->default(0);
                $t->decimal('subtotal', 14, 2);
                $t->string('motivo_item', 200)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones_proveedor_items');
        Schema::dropIfExists('devoluciones_proveedor');
    }
};
