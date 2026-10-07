<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache local del catálogo /v1/taxes de SIIGO.
 *
 * SIIGO expone 114+ taxes en 9 tipos (IVA, Retefuente, ReteICA, ReteIVA,
 * Impoconsumo, AdValorem, Autorretencion, Bebidas azucaradas, Comestibles
 * ultraprocesados). Los ids son del tenant: `1270` que ahora usamos como
 * IVA 19% probablemente NO sea el mismo id en la cuenta real de Great Baby.
 * Esta tabla se refresca con `php artisan siigo:sync-taxes` tras conectar
 * la cuenta real, y alimenta los selectores de settings `siigo.tax_id_*`
 * en el panel Reglas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siigo_taxes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('siigo_id')->unique();
            $t->string('type', 60)->index();
            $t->string('name', 180);
            $t->decimal('percentage', 7, 4)->default(0);
            $t->boolean('active')->default(true);
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siigo_taxes');
    }
};
