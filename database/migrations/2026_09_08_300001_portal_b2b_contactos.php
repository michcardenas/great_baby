<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $t) {
            $t->string('password')->nullable()->after('email');
            $t->string('remember_token', 100)->nullable()->after('password');
            $t->foreignId('lista_precios_id')->nullable()->after('remember_token')
                ->constrained('listas_precios')->nullOnDelete();
            $t->boolean('portal_habilitado')->default(false)->after('lista_precios_id');
            $t->timestamp('ultimo_login_at')->nullable()->after('portal_habilitado');
            $t->string('reset_token', 100)->nullable()->after('ultimo_login_at');
            $t->timestamp('reset_token_at')->nullable()->after('reset_token');
            $t->index('portal_habilitado');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $t) {
            $t->dropForeign(['lista_precios_id']);
            $t->dropIndex(['portal_habilitado']);
            $t->dropColumn([
                'password', 'remember_token', 'lista_precios_id',
                'portal_habilitado', 'ultimo_login_at', 'reset_token', 'reset_token_at',
            ]);
        });
    }
};
