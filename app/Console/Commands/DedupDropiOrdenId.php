<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-audit DR-κ (DATOS-M4) · dedup dropi_orden_id previo al UNIQUE.
 *
 *   La migración 240001 intenta agregar UNIQUE en `dropi_pedidos.dropi_orden_id`
 *   pero si hay duplicados legacy (mismo id de orden con guías distintas por
 *   error histórico) la migración falla y hace catch silencioso — el UNIQUE
 *   queda sin instalar.
 *
 *   Este comando anula (`dropi_orden_id = NULL`) los duplicados dejando sólo
 *   el pedido con `id` más BAJO (el más viejo, presumiblemente el legítimo).
 *   Después de correrlo, se puede re-lanzar la migración o el ALTER a mano.
 */
class DedupDropiOrdenId extends Command
{
    protected $signature = 'dropi:dedup-orden-id
        {--dry-run : Solo listar duplicados, no anular}';

    protected $description = 'Anula dropi_orden_id de duplicados dejando el pedido más antiguo. Requerido antes de instalar UNIQUE.';

    public function handle(): int
    {
        $duplicados = DB::table('dropi_pedidos')
            ->select('dropi_orden_id', DB::raw('COUNT(*) as n'), DB::raw('MIN(id) as keep_id'), DB::raw('GROUP_CONCAT(id) as ids'))
            ->whereNotNull('dropi_orden_id')
            ->where('dropi_orden_id', '!=', '')
            ->groupBy('dropi_orden_id')
            ->having('n', '>', 1)
            ->get();

        if ($duplicados->isEmpty()) {
            $this->info('Sin duplicados de dropi_orden_id. Nada por hacer.');
            return self::SUCCESS;
        }

        $this->table(
            ['dropi_orden_id', 'copias', 'keep_id', 'todos_ids'],
            $duplicados->map(fn ($d) => [$d->dropi_orden_id, $d->n, $d->keep_id, $d->ids])->toArray()
        );

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN: no se modificó nada. Corre sin --dry-run para anular los duplicados.');
            return self::SUCCESS;
        }

        if (! $this->confirm('¿Anular dropi_orden_id de los duplicados dejando sólo el keep_id?')) {
            return self::SUCCESS;
        }

        $anulados = 0;
        foreach ($duplicados as $d) {
            $ids = array_map('intval', explode(',', $d->ids));
            $ids = array_filter($ids, fn ($id) => $id !== (int) $d->keep_id);
            if (empty($ids)) continue;
            $anulados += DB::table('dropi_pedidos')
                ->whereIn('id', $ids)
                ->update(['dropi_orden_id' => null, 'updated_at' => now()]);
        }

        $this->info("Anulados: {$anulados} pedidos. Ahora puedes re-lanzar el ALTER UNIQUE.");
        return self::SUCCESS;
    }
}
