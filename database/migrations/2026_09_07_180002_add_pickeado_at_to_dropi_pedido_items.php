<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropi_pedido_items', function (Blueprint $table) {
            $table->timestamp('pickeado_at')->nullable()->after('despachado');
            $table->foreignId('pickeado_por')->nullable()->after('pickeado_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('dropi_pedido_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickeado_por');
            $table->dropColumn('pickeado_at');
        });
    }
};
