<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliaciones_bancarias', function (Blueprint $table) {
            $table->json('adjuntos')->nullable()->after('notas')
                ->comment('Rutas de archivos de soporte (extracto bancario, etc.)');
        });
    }

    public function down(): void
    {
        Schema::table('conciliaciones_bancarias', function (Blueprint $table) {
            $table->dropColumn('adjuntos');
        });
    }
};
