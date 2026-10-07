<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache local del catálogo /v1/document-types de SIIGO.
 *
 * SIIGO expone ~500 tipos de documento repartidos en FC/FV/NC/ND/RP/DS/CC/RC.
 * Los ids son del tenant, no son estándar: el `2377` que vemos en sandbox
 * NO es el mismo id de "Compra" en la cuenta real de Great Baby. Esta tabla
 * se refresca con `php artisan siigo:sync-document-types` tras conectar la
 * cuenta real, y alimenta los selectores del panel Reglas para que la
 * contadora elija el documento correcto sin pegar ids a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siigo_document_types', function (Blueprint $t) {
            $t->id();
            $t->string('type', 4);
            $t->unsignedBigInteger('siigo_id');
            $t->string('code', 20)->nullable();
            $t->string('name', 180);
            $t->boolean('active')->default(true);
            $t->boolean('cost_center')->default(false);
            $t->boolean('cost_center_mandatory')->default(false);
            $t->boolean('automatic_number')->default(false);
            $t->integer('consecutive')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
            $t->unique(['type', 'siigo_id']);
            $t->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siigo_document_types');
    }
};
