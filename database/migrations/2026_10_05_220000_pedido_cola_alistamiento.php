<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOG-J5 · Cola Don Jorge · alistamiento con huella.
 *
 * Don Jorge llevaba una hoja de Excel manual: número de pedido · alistador ·
 * hora inicio · hora fin · observación. Pasamos esa huella al pedido mismo
 * para que la línea de tiempo (LOG-J10) se arme sola y para que la vista
 * "Cola Jorge" pueda filtrar y ordenar por prioridad sin tener que mantener
 * una tabla paralela.
 *
 * Un pedido se asigna cuando entra en picking, se inicia cuando el alistador
 * agarra la caja, y se finaliza cuando entrega al empacador. La UI muestra
 * tres columnas (asignado / en curso / terminado) que es exactamente el
 * tablero que Jorge dibujó en la reunión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->foreignId('alistador_id')->nullable()->after('despachado_por_id')
                ->constrained('users')->nullOnDelete();
            $t->timestamp('alistado_asignado_at')->nullable()->after('alistador_id');
            $t->timestamp('alistado_inicio_at')->nullable()->after('alistado_asignado_at');
            $t->timestamp('alistado_fin_at')->nullable()->after('alistado_inicio_at');
            $t->text('alistado_notas')->nullable()->after('alistado_fin_at');

            // Índice para que la Cola Jorge filtre rápido por alistador en curso.
            $t->index(['alistador_id', 'alistado_fin_at'], 'idx_cola_jorge');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->dropIndex('idx_cola_jorge');
            $t->dropConstrainedForeignId('alistador_id');
            $t->dropColumn(['alistado_asignado_at', 'alistado_inicio_at', 'alistado_fin_at', 'alistado_notas']);
        });
    }
};
