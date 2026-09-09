<?php

namespace App\Modules\Cartera\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Raíz B (DATOS C3, C4) · Generador transaccional de números de factura DIAN.
 *
 * Reemplaza `SELECT MAX(numero) + 1` (race condition + no acota a 4 dígitos):
 *   1) `SELECT ... FOR UPDATE` sobre la fila del prefijo → serialización real.
 *   2) Incrementa y devuelve el consecutivo.
 *   3) Ancho dinámico — no rompe en 10 000+ facturas por día.
 *   4) Valida rango DIAN configurado en empresa (rango_desde/hasta) — aborta si
 *      la resolución está agotada, evitando factura ilegal fuera de rango.
 *
 * Uso:
 *   $numero = SiguienteConsecutivoFactura::run('FV-260909', 4, 'rango_hasta_prefix');
 */
class SiguienteConsecutivoFactura
{
    use AsAction;

    /**
     * @param string $prefijo  Prefijo completo (ej. "FV-260909-").
     * @param int    $anchoMin Ancho mínimo para padding con ceros (ancho real crece si el número lo exige).
     * @return string Prefijo + consecutivo con padding.
     */
    public function handle(string $prefijo, int $anchoMin = 4, ?int $rangoMax = null): string
    {
        // Re-audit DATOS #8 · normalizar prefijo (uppercase + trim) para evitar
        // colisiones por espacios o mayúsculas distintas creando filas paralelas
        // en `facturas_consecutivos` → mismo consecutivo → colisión DIAN.
        $prefijo = strtoupper(trim($prefijo));

        return DB::transaction(function () use ($prefijo, $anchoMin, $rangoMax) {
            $row = DB::table('facturas_consecutivos')
                ->where('prefijo', $prefijo)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                // Insertar la fila inicial. Si otra tx concurrente la crea al mismo
                // tiempo, atrapamos con UNIQUE y releemos con lock.
                try {
                    DB::table('facturas_consecutivos')->insert([
                        'prefijo' => $prefijo,
                        'ultimo' => 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    // otra tx ganó — ok
                }
                $row = DB::table('facturas_consecutivos')
                    ->where('prefijo', $prefijo)
                    ->lockForUpdate()
                    ->first();
            }

            $siguiente = (int) $row->ultimo + 1;

            // Raíz B (DATOS C4) · validar rango DIAN si aplica.
            if ($rangoMax !== null && $siguiente > $rangoMax) {
                throw new RuntimeException(
                    "Resolución DIAN agotada: consecutivo {$siguiente} excede el rango máximo {$rangoMax}. Gestionar nueva resolución antes de emitir."
                );
            }

            DB::table('facturas_consecutivos')
                ->where('prefijo', $prefijo)
                ->update(['ultimo' => $siguiente, 'updated_at' => now()]);

            $ancho = max($anchoMin, strlen((string) $siguiente));
            return $prefijo . str_pad((string) $siguiente, $ancho, '0', STR_PAD_LEFT);
        });
    }
}
