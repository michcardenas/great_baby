<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QA-FIX #4 · notas_credito.motivo era varchar(200) pero controller valida
 * max:500 → truncación silenciosa o error 1406. Alineamos a 500 (match ND).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_credito', function (Blueprint $t) {
            $t->string('motivo', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('notas_credito', function (Blueprint $t) {
            $t->string('motivo', 200)->change();
        });
    }
};
