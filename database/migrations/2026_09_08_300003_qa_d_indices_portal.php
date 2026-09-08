<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA-D Bloque 3 · Índices y unique de refuerzo:
 *  - pedidos_cliente_items(pedido_id, variante_id) — evita duplicado por doble-click frontend.
 *  - contactos.email UNIQUE parcial (solo cuando portal_habilitado=true) — impide dos portales con mismo mail.
 * Nota: no forzamos email unique global porque otros contactos (proveedores, empleados) pueden compartir email.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Deduplicar posibles duplicados antes de crear el índice único.
        $dupes = DB::select("
            SELECT pedido_id, variante_id, COUNT(*) as c
            FROM pedidos_cliente_items
            GROUP BY pedido_id, variante_id
            HAVING c > 1
        ");
        foreach ($dupes as $d) {
            // Conservar el primero, borrar los demás con id mayor.
            DB::statement("
                DELETE FROM pedidos_cliente_items
                WHERE pedido_id = ? AND variante_id = ?
                  AND id NOT IN (
                    SELECT * FROM (
                        SELECT MIN(id) FROM pedidos_cliente_items
                        WHERE pedido_id = ? AND variante_id = ?
                    ) x
                  )
            ", [$d->pedido_id, $d->variante_id, $d->pedido_id, $d->variante_id]);
        }

        Schema::table('pedidos_cliente_items', function (Blueprint $t) {
            $t->unique(['pedido_id', 'variante_id'], 'pci_pedido_variante_uniq');
        });

        // email unique parcial para portales habilitados — MariaDB soporta vía columna generada.
        // Truco: creamos una columna virtual `email_portal_key` = email si portal_habilitado=1 sino NULL,
        // y le ponemos UNIQUE (NULL no colisiona en MariaDB).
        // Primero deduplicar posibles duplicados actuales.
        $dupesEmail = DB::select("
            SELECT LOWER(email) as email, COUNT(*) as c
            FROM contactos
            WHERE portal_habilitado = 1 AND email IS NOT NULL AND deleted_at IS NULL
            GROUP BY LOWER(email)
            HAVING c > 1
        ");
        foreach ($dupesEmail as $d) {
            // Conservar el más reciente activo, desactivar los demás.
            $ids = DB::table('contactos')
                ->whereRaw('LOWER(email) = ?', [$d->email])
                ->where('portal_habilitado', true)
                ->whereNull('deleted_at')
                ->orderByDesc('ultimo_login_at')
                ->orderByDesc('updated_at')
                ->pluck('id')->all();
            $conservar = array_shift($ids);
            if ($ids) {
                DB::table('contactos')->whereIn('id', $ids)->update(['portal_habilitado' => false]);
            }
        }

        Schema::table('contactos', function (Blueprint $t) {
            $t->string('email_portal_key')->virtualAs("CASE WHEN portal_habilitado = 1 THEN LOWER(email) ELSE NULL END")->nullable();
            $t->unique('email_portal_key', 'contactos_email_portal_uniq');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $t) {
            $t->dropUnique('contactos_email_portal_uniq');
            $t->dropColumn('email_portal_key');
        });
        Schema::table('pedidos_cliente_items', function (Blueprint $t) {
            $t->dropUnique('pci_pedido_variante_uniq');
        });
    }
};
