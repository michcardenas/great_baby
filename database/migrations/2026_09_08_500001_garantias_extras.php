<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garantia_tickets', function (Blueprint $t) {
            $t->text('notas_decision')->nullable()->after('descripcion_falla');
            $t->json('fotos_evidencia')->nullable()->after('notas_decision'); // ["path1","path2",...]
            $t->decimal('valor_reposicion', 12, 2)->default(0)->after('fotos_evidencia'); // $0 según contrato
            $t->foreignId('pedido_reposicion_id')->nullable()->after('valor_reposicion')
                ->constrained('dropi_pedidos')->nullOnDelete();
            $t->string('numero', 30)->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('garantia_tickets', function (Blueprint $t) {
            $t->dropForeign(['pedido_reposicion_id']);
            $t->dropColumn(['notas_decision', 'fotos_evidencia', 'valor_reposicion', 'pedido_reposicion_id', 'numero']);
        });
    }
};
