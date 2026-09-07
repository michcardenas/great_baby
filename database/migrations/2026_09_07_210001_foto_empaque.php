<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empaques_registro', function (Blueprint $table) {
            $table->string('foto_path', 255)->nullable()->after('notas');
            $table->timestamp('foto_at')->nullable()->after('foto_path');
        });
    }

    public function down(): void
    {
        Schema::table('empaques_registro', function (Blueprint $table) {
            $table->dropColumn(['foto_path', 'foto_at']);
        });
    }
};
