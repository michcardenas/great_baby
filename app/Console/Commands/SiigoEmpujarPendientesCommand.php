<?php

namespace App\Console\Commands;

use App\Modules\Siigo\Models\SiigoConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Red de seguridad: encola TODO documento que debería estar en SIIGO y no está.
 *
 * Los observers empujan cada documento al crearlo, pero eso se pierde si en ese
 * momento la cola estaba caída, SIIGO no respondía, faltaba una cuenta por
 * mapear o el kill-switch estaba apagado. El documento queda en el ERP sin
 * `siigo_id` y nadie vuelve a intentarlo: así se acumularon 104 documentos.
 *
 * Este comando barre esos huecos y reencola. Es idempotente — cada job revisa
 * de nuevo si el documento ya tiene id — y corre a diario por el scheduler.
 *
 *   php artisan siigo:empujar-pendientes --dry-run
 *   php artisan siigo:empujar-pendientes --tipo=facturas
 *   php artisan siigo:empujar-pendientes --limite=50
 */
class SiigoEmpujarPendientesCommand extends Command
{
    protected $signature = 'siigo:empujar-pendientes
                            {--dry-run : Sólo muestra qué se encolaría}
                            {--tipo= : Un solo tipo (facturas, notas-credito, notas-debito, recepciones, pagos-cliente, pagos-proveedor, asientos, movimientos, devoluciones)}
                            {--limite=200 : Máximo de documentos por tipo}';

    protected $description = 'Reencola a SIIGO todo documento que quedó sin enviar';

    /**
     * tipo => [tabla, columna que prueba que llegó, job, condición extra]
     */
    private const FUENTES = [
        'facturas' => [
            'facturas_venta', 'siigo_id', \App\Jobs\ReintentarEmisionDian::class,
            "es_electronica = 1 AND estado <> 'anulada'",
        ],
        'notas-credito' => [
            'notas_credito', 'siigo_id', \App\Modules\Siigo\Jobs\PushNotaCreditoASiigo::class,
            // Las NC de devoluciones Dropi se excluyen a propósito: ese flujo no
            // está implementado y encolarlas sólo llenaría la cola de fallos.
            "estado <> 'borrador' AND devolucion_dropi_id IS NULL",
        ],
        'notas-debito' => [
            'notas_debito', 'siigo_id', \App\Modules\Siigo\Jobs\PushNotaDebitoASiigo::class,
            "estado <> 'borrador'",
        ],
        'recepciones' => [
            'compras_recepciones', 'siigo_id', \App\Modules\Siigo\Jobs\PushRecepcionASiigo::class,
            "estado = 'confirmada'",
        ],
        'pagos-cliente' => [
            'pagos_venta', 'siigo_id', \App\Modules\Siigo\Jobs\PushVoucherASiigo::class,
            'deleted_at IS NULL',
        ],
        'pagos-proveedor' => [
            'pagos_proveedor', 'siigo_voucher_id', \App\Modules\Siigo\Jobs\PushPagoProveedorASiigo::class,
            "estado = 'confirmado'",
        ],
        'asientos' => [
            'asientos_manuales', 'siigo_journal_id', \App\Modules\Siigo\Jobs\PushAsientoManualASiigo::class,
            "estado = 'aprobado'",
        ],
        // Movimientos de INVENTARIO (kardex). Son los únicos que necesitan un
        // asiento propio en SIIGO: ajustes de toma física, traslados y
        // devoluciones no nacen de ningún documento que SIIGO contabilice solo.
        //
        // OJO · `movimientos_contables` NO va acá aunque tenga `siigo_journal_id`:
        // sus filas son la contrapartida de facturas, pagos, recepciones y
        // devoluciones, y SIIGO ya genera ese asiento al crear cada documento.
        // Empujarlas duplicaría la contabilidad.
        'movimientos' => [
            'inventario_movimientos', 'siigo_journal_id', \App\Modules\Siigo\Jobs\PushAsientoASiigo::class,
            "tipo IN ('ajuste_toma_fisica','traslado_salida','traslado_entrada',"
                ."'traslado_reversa_salida','traslado_reversa_entrada')",
        ],
        'devoluciones' => [
            'devoluciones_proveedor', 'siigo_id', \App\Modules\Siigo\Jobs\PushDevolucionProveedorASiigo::class,
            "estado = 'confirmada'",
        ],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limite = max(1, (int) $this->option('limite'));
        $soloTipo = $this->option('tipo');

        if ($soloTipo && ! isset(self::FUENTES[$soloTipo])) {
            $this->error("Tipo desconocido: {$soloTipo}. Opciones: ".implode(', ', array_keys(self::FUENTES)));
            return self::FAILURE;
        }

        if (! $dryRun && ! SiigoConfig::pushAutoActivo()) {
            $this->warn('El envío automático a SIIGO está apagado (FEATURE_SIIGO_PUSH_AUTO).');
            $this->warn('Los jobs se encolarían y se auto-omitirían. Activalo primero o usá --dry-run.');
            return self::FAILURE;
        }

        $cola = config('siigo.queue', 'siigo');
        $totalEncolados = 0;
        $filas = [];

        foreach (self::FUENTES as $tipo => [$tabla, $columna, $job, $filtro]) {
            if ($soloTipo && $tipo !== $soloTipo) {
                continue;
            }
            if (! $this->fuenteUsable($tabla, $columna, $filtro)) {
                $filas[] = [$tipo, $tabla, '—', '—', 'no aplica en este esquema'];
                continue;
            }

            $ids = DB::table($tabla)
                ->whereRaw($filtro)
                ->where(fn ($q) => $q->whereNull($columna)->orWhere($columna, ''))
                ->orderBy('id')
                ->limit($limite)
                ->pluck('id');

            $pendientes = $ids->count();
            $encolados = 0;

            if (! $dryRun) {
                foreach ($ids as $id) {
                    try {
                        $job::dispatch((int) $id)->onQueue($cola);
                        $encolados++;
                    } catch (\Throwable $e) {
                        $this->warn("  {$tipo}#{$id}: ".$e->getMessage());
                    }
                }
            }

            $totalEncolados += $encolados;
            $filas[] = [$tipo, $tabla, $pendientes, $dryRun ? '(dry-run)' : $encolados, class_basename($job)];
        }

        $this->table(['Tipo', 'Tabla', 'Pendientes', 'Encolados', 'Job'], $filas);

        if ($dryRun) {
            $this->info('Dry-run: no se encoló nada. Quitá --dry-run para enviarlos.');
            return self::SUCCESS;
        }

        $this->info("{$totalEncolados} documentos encolados en la cola «{$cola}».");
        $this->line('Procesalos con: php artisan queue:work --queue='.$cola);

        return self::SUCCESS;
    }

    /** El esquema varía entre instalaciones; no asumimos que la columna exista. */
    private function fuenteUsable(string $tabla, string $columna, string $filtro): bool
    {
        if (! \Schema::hasTable($tabla) || ! \Schema::hasColumn($tabla, $columna)) {
            return false;
        }
        if ($filtro === '1=1') {
            return true;
        }
        // El filtro nombra columnas que pueden no existir (ej. devolucion_dropi_id).
        try {
            DB::table($tabla)->whereRaw($filtro)->limit(1)->exists();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
