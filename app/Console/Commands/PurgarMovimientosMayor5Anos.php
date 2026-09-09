<?php

namespace App\Console\Commands;

use App\Modules\Cartera\Models\MovimientoContable;
use Illuminate\Console\Command;

/**
 * Re-audit R3 · comando de retención DIAN.
 *
 * DIAN exige 5 años de retención sobre libros y documentos electrónicos.
 * Este comando purga físicamente movimientos_contables soft-deleted cuya
 * `deleted_at` sea mayor a 5 años + margen. Los movs vivos NO se tocan
 * NUNCA (obligación legal). Solo limpia el "cementerio".
 *
 * Ejecución sugerida: 1 vez al año en `app/Console/Kernel.php`:
 *   $schedule->command('movs:purgar-mayor-5anos')->yearlyOn(1, 1, '02:00');
 */
class PurgarMovimientosMayor5Anos extends Command
{
    protected $signature = 'movs:purgar-mayor-5anos {--dry-run : Solo cuenta, no borra}';
    protected $description = 'Purga físicamente movimientos_contables soft-deleted con más de 5 años (retención DIAN).';

    public function handle(): int
    {
        $corte = now()->subYears(5)->subMonths(3); // margen de 3 meses
        $q = MovimientoContable::onlyTrashed()->where('deleted_at', '<', $corte);
        $count = (int) $q->count();

        $this->info(sprintf('Corte: %s. Filas candidatas: %d', $corte->toDateString(), $count));

        if ($count === 0) return self::SUCCESS;
        if ($this->option('dry-run')) {
            $this->line('[dry-run] no se borra nada.');
            return self::SUCCESS;
        }

        $borrado = $q->forceDelete();
        $this->info(sprintf('Purgado físico: %d filas.', $borrado));

        return self::SUCCESS;
    }
}
