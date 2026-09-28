<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 · B.3 · Configuración parametrizable de retenciones (Retefuente + Reteica + Reteiva).
 *
 * Reglas típicas Colombia (ejemplos que Aracely/Silvia configurarán):
 *   - Retefuente compras generales: 2.5% desde base 27 UVT · cuenta 236540
 *   - Retefuente servicios: 4% o 6% desde 4 UVT · cuenta 236525
 *   - Reteica Bogotá servicios: 9.66x1000 · cuenta 236805
 *   - Reteiva grandes contribuyentes: 15% del IVA · cuenta 236701
 *
 * La tabla `retenciones_aplicadas` registra cada aplicación real (log auditable).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('retenciones_config')) {
            Schema::create('retenciones_config', function (Blueprint $t) {
                $t->id();
                $t->enum('tipo', ['retefuente', 'reteica', 'reteiva'])->index();
                $t->string('concepto', 100); // "Compras generales", "Servicios", "Honorarios", etc.
                $t->string('ciudad', 60)->nullable(); // Solo aplica a Reteica
                $t->decimal('base_minima', 14, 2)->default(0); // Base mínima en pesos (o UVT convertido)
                $t->decimal('tarifa_pct', 6, 3); // Ej: 2.500 = 2.5%
                $t->string('cuenta_puc', 30); // Cuenta contable donde debita la retención
                $t->boolean('activa')->default(true);
                $t->text('notas')->nullable();
                $t->timestamps();
                $t->index(['tipo', 'activa']);
            });
        }

        if (! Schema::hasTable('retenciones_aplicadas')) {
            Schema::create('retenciones_aplicadas', function (Blueprint $t) {
                $t->id();
                $t->morphs('origen'); // FacturaCompra, PagoVenta, RecepcionCompra
                $t->foreignId('config_id')->constrained('retenciones_config')->restrictOnDelete();
                $t->enum('tipo', ['retefuente', 'reteica', 'reteiva']);
                $t->decimal('base', 14, 2);
                $t->decimal('tarifa_pct', 6, 3);
                $t->decimal('valor', 14, 2);
                $t->string('cuenta_puc', 30);
                $t->string('siigo_id', 60)->nullable(); // Para trazabilidad con retenciones SIIGO
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retenciones_aplicadas');
        Schema::dropIfExists('retenciones_config');
    }
};
