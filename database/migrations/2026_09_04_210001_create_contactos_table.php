<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contacto unificado — un solo registro sirve como cliente B2B, proveedor, empleado, vendedor.
 * Evita duplicar información al construir Compras (proveedor), Cartera (cliente), RRHH (empleado).
 * Roles se marcan como flags booleanos (un contacto puede ser varios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 15)->default('CC')->comment('CC | CE | NIT | PP');
            $table->string('numero_documento', 30)->unique();
            $table->string('nombre_completo', 180);
            $table->string('razon_social', 180)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->string('ciudad', 80)->nullable();
            $table->string('departamento', 80)->nullable();

            // Flags de rol (un contacto puede ser varios)
            $table->boolean('es_cliente')->default(false);
            $table->boolean('es_cliente_b2b')->default(false);
            $table->boolean('es_proveedor')->default(false);
            $table->boolean('es_empleado')->default(false);
            $table->boolean('es_vendedor_dropi')->default(false);

            // Fiscal (Colombia)
            $table->string('regimen_iva', 20)->nullable()->comment('responsable | no_responsable');
            $table->json('retenciones_default')->nullable()->comment('{fuente, ica, iva} — futuro cálculo automático');

            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['es_cliente', 'activo']);
            $table->index(['es_proveedor', 'activo']);
            $table->index(['es_empleado', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
