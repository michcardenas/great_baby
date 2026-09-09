<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ronda 2 fixes re-audit M4:
 *   SEG N2  · movimientos_contables.deleted_at (SoftDeletes).
 *   DATOS #7 · notas_credito.prefijo VARCHAR(40) (antes 10 cortaba prefijos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_contables', function (Blueprint $table) {
            if (! Schema::hasColumn('movimientos_contables', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at');
            }
        });

        Schema::table('notas_credito', function (Blueprint $table) {
            $table->string('prefijo', 40)->change();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_contables', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_contables', 'deleted_at')) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            }
        });
        Schema::table('notas_credito', function (Blueprint $table) {
            $table->string('prefijo', 10)->change();
        });
    }
};
