<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE F2.A4 · Permite sobrescribir `account_group` SIIGO por producto
 * (antes solo se podía por categoría local). El selector "Grupo de inventario
 * SIIGO" del form del producto ahora persiste a este campo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            if (! Schema::hasColumn('productos', 'siigo_account_group_override')) {
                $t->unsignedInteger('siigo_account_group_override')
                    ->nullable()
                    ->after('impuesto_cargo_dos_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            if (Schema::hasColumn('productos', 'siigo_account_group_override')) {
                $t->dropColumn('siigo_account_group_override');
            }
        });
    }
};
