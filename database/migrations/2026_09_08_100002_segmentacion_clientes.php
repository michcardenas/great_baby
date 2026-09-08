<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1-B · Segmentación automática de clientes.
 * Se recalcula semanal vía Schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            if (! Schema::hasColumn('contactos', 'segmento')) {
                $table->string('segmento', 20)->nullable()
                    ->comment('vip|frecuente|nuevo|dormido|en_riesgo|inactivo');
            }
            if (! Schema::hasColumn('contactos', 'segmento_score')) {
                $table->decimal('segmento_score', 5, 2)->default(0);
            }
            if (! Schema::hasColumn('contactos', 'ticket_promedio')) {
                $table->decimal('ticket_promedio', 14, 2)->default(0);
            }
            if (! Schema::hasColumn('contactos', 'ultima_compra_at')) {
                $table->date('ultima_compra_at')->nullable();
            }
            if (! Schema::hasColumn('contactos', 'total_comprado_ytd')) {
                $table->decimal('total_comprado_ytd', 14, 2)->default(0);
            }
            if (! Schema::hasColumn('contactos', 'segmentado_at')) {
                $table->timestamp('segmentado_at')->nullable();
            }
            $table->index('segmento');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            $table->dropIndex(['segmento']);
            $table->dropColumn(['segmento', 'segmento_score', 'ticket_promedio',
                'ultima_compra_at', 'total_comprado_ytd', 'segmentado_at']);
        });
    }
};
