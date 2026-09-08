<?php

namespace App\Modules\Cartera\Actions;

use App\Modules\Dropi\Models\DropiDevolucion;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * REU-3: Auto-generar Nota Crédito al recibir una devolución Dropi.
 * Aracely: "si es la devolución, de una vez ya haga la nota crédito, se envíe y ya, o sea, ya tú la tengas."
 *
 * Reglas configurables:
 *   dropi.auto_nota_credito        → activar/desactivar auto-fire
 *   dropi.auto_nc_envia_siigo      → tras crear, enviar a SIIGO
 */
class EmitirNotaCreditoDropi
{
    use AsAction;

    public function handle(DropiDevolucion $devolucion): ?array
    {
        // Si la regla está OFF: resetear el pre-flag mentiroso que dejó RegistrarDevolucion
        // (línea 49 pone genero_nota_credito=(bool)$pedido->ari_factura_id sin haber ejecutado NC).
        if (! (bool) setting('dropi.auto_nota_credito', true)) {
            if ($devolucion->genero_nota_credito) {
                $devolucion->update([
                    'genero_nota_credito' => false,
                    'notas' => trim(($devolucion->notas ?? '') . "\n[NC skip] regla dropi.auto_nota_credito=false"),
                ]);
            }
            return ['status' => 'skip_regla_off'];
        }

        $pedido = $devolucion->pedido;
        if (! $pedido) return null;

        // Si ya se registró NC previa, no duplicar
        if ($devolucion->genero_nota_credito && $devolucion->nota_credito_ari_id) {
            return ['status' => 'ya_generada', 'ref' => $devolucion->nota_credito_ari_id];
        }

        // Fix S-04 (auditor Sec): sin factura ARI/SIIGO no puede haber NC, sería fantasma.
        if (! $pedido->ari_factura_id) {
            $devolucion->update([
                'genero_nota_credito' => false,
                'notas' => trim(($devolucion->notas ?? '') . "\n[NC skip] pedido sin factura previa"),
            ]);
            return ['status' => 'skip_sin_factura'];
        }

        $valor = (float) $pedido->monto_esperado_proveedor;
        if ($valor <= 0) {
            $devolucion->update([
                'genero_nota_credito' => false,
                'notas' => trim(($devolucion->notas ?? '') . "\n[NC skip] pedido con monto=0"),
            ]);
            Log::info('[NC-Dropi] Devolución sin valor; skip', ['pedido' => $pedido->guia]);
            return null;
        }

        // Coordinar dispatch + update del flag DENTRO de afterCommit —
        // ambos se aplican solo si la tx padre commitea. Si dispatch falla,
        // el flag no se sube (evita el estado inconsistente que el try/catch
        // externo no podía capturar, ya que afterCommit registra callback diferido).
        $refInterna = 'NC-DRP-' . $pedido->guia . '-' . now()->format('YmdHis');
        $devId = $devolucion->id;
        \Illuminate\Support\Facades\DB::afterCommit(function () use ($devId, $refInterna, $pedido, $valor) {
            try {
                \App\Modules\Cartera\Jobs\EnviarNotaCreditoSiigo::dispatch($devId, $refInterna);
                // Solo tras dispatch OK subir el flag (via query directa para no re-disparar observers).
                \App\Modules\Dropi\Models\DropiDevolucion::whereKey($devId)->update([
                    'genero_nota_credito' => true,
                    'nota_credito_ari_id' => $refInterna,
                ]);
                Log::info('[NC-Dropi] Nota crédito encolada', ['pedido' => $pedido->guia, 'valor' => $valor, 'ref' => $refInterna]);
            } catch (\Throwable $e) {
                Log::error('[NC-Dropi] Fallo dispatch post-commit', ['pedido' => $pedido->guia, 'error' => $e->getMessage()]);
                // Marcar en notas para trazabilidad (no crashea el flujo padre — ya commiteó).
                \App\Modules\Dropi\Models\DropiDevolucion::whereKey($devId)->update([
                    'notas' => \Illuminate\Support\Facades\DB::raw("CONCAT(COALESCE(notas,''), '\n[NC ERROR dispatch] " . addslashes($e->getMessage()) . "')"),
                ]);
            }
        });

        return ['status' => 'encolada_pending_commit', 'ref' => $refInterna, 'valor' => $valor];
    }
}
