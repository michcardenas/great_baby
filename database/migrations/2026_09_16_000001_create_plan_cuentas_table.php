<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de PLAN DE CUENTAS (PUC) — dominio de Silvia (Contabilidad).
 *
 * Antes `cuenta_puc` vivía como texto libre en movimientos_contables. Este catálogo
 * lo vuelve gestionable: Silvia carga SU plan (manual o import Excel/CSV), y las
 * cuentas quedan clasificadas por clase/nivel/naturaleza para filtrar reportes,
 * validar asientos y mapear a SIIGO.
 *
 * Niveles PUC (Decreto 2650): 1=Clase(1 díg), 2=Grupo(2), 3=Cuenta(4),
 * 4=Subcuenta(6), 5=Auxiliar(7+).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_cuentas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique()->comment('Código PUC solo dígitos. Ej: 1105, 4135, 413505');
            $table->string('nombre', 150);
            $table->string('clase', 1)->nullable()->index()->comment('1..9 (primer dígito del código)');
            $table->unsignedTinyInteger('nivel')->default(1)->comment('1=Clase 2=Grupo 3=Cuenta 4=Subcuenta 5=Auxiliar');
            $table->string('naturaleza', 8)->default('debito')->comment('debito|credito');
            $table->foreignId('padre_id')->nullable()->constrained('plan_cuentas')->nullOnDelete();
            $table->boolean('permite_movimiento')->default(true)->comment('Solo cuentas de detalle (hoja) reciben asientos');
            $table->string('siigo_cuenta_id', 40)->nullable()->comment('Mapeo a la cuenta equivalente en SIIGO');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_cuentas');
    }
};
