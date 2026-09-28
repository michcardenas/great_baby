<?php

namespace App\Modules\Siigo\Actions;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoSyncLog;
use App\Modules\Siigo\Support\ProductoPayloadBuilder;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Soft-delete en Siigo · marca el producto (o cada variante) como active=false
 * mediante PUT. SIIGO devuelve `delete_not_allowed` si se hace DELETE real y
 * el producto tiene movimientos, así que siempre preferimos desactivar.
 *
 * Fixes auditoría:
 *   A2 · duracion_ms real medida.
 *   A5 · desglose_stock null → default explícito.
 *   A6 · setRelation('producto') antes de iterar variantes.
 *   M5 · Log guarda response_body.
 *   M6 · Log resumen incluso si desactivados=0 (para dejar rastro).
 */
class DesactivarProductoEnSiigo
{
    use AsAction;

    public function __construct(
        private readonly SiigoClient $client,
        private readonly ProductoPayloadBuilder $builder,
    ) {}

    public function handle(Producto $producto): array
    {
        // B3-P1 · eager preciosVigentes también para el desactivar granular
        // (usa el mismo builder que arma payload completo por variante).
        $producto->loadMissing([
            'variantes.preciosVigentes',
            'marca',
            'categoriaMaestra',
            'impuesto',
            'unidadMedida',
        ]);
        $t0 = microtime(true);

        $desactivados = 0;
        $errores = 0;
        $sinSiigoId = 0;
        $detalle = [];

        if ($this->esGranular($producto)) {
            foreach ($producto->variantes as $v) {
                if (! $v->siigo_id) { $sinSiigoId++; continue; }
                // A6 · pre-cargar
                $v->setRelation('producto', $producto);

                try {
                    $payload = array_merge($this->builder->paraVariante($v), ['active' => false]);
                } catch (\Throwable $e) {
                    $errores++;
                    $detalle[] = ['variante' => $v->codigo_barras, 'error' => $e->getMessage()];
                    continue;
                }

                $response = $this->client->request('PUT', "/v1/products/{$v->siigo_id}", $payload);

                // B2-A3 · SIIGO devuelve update_not_allowed cuando el producto ya
                // pasó por un cierre contable · no es un error nuestro, es un
                // estado esperado. Antes lo tratábamos como fallido y cada intento
                // loggeaba `fallido` para siempre. Ahora `ignorado`.
                if ($response->failed() && $this->esUpdateNotAllowed($response->json())) {
                    $this->log($producto, 'desactivar-variante', 'ignorado', $response->status(),
                        'update_not_allowed (variante con cierre contable)', $payload, $response->json(), $t0, $v->id);
                    continue;
                }

                if ($response->failed()) {
                    $errores++;
                    $detalle[] = ['variante' => $v->codigo_barras, 'error' => $response->status()];
                    $this->log($producto, 'desactivar-variante', 'fallido', $response->status(),
                        'PUT active:false falló', $payload, $response->json(), $t0, $v->id);
                    continue;
                }

                $v->forceFill(['siigo_sync_at' => now()])->save();
                $desactivados++;
            }
        } else {
            if (! $producto->siigo_id) {
                $sinSiigoId = 1;
                $this->log($producto, 'desactivar-agregado', 'ignorado', 0,
                    'sin siigo_id (nunca sincronizado)', [], null, $t0);
                return ['skipped' => true, 'motivo' => 'sin siigo_id', 'desactivados' => 0];
            }

            try {
                $payload = array_merge($this->builder->paraProducto($producto), ['active' => false]);
            } catch (\Throwable $e) {
                $this->log($producto, 'desactivar-agregado', 'fallido', 0, 'builder: '.$e->getMessage(),
                    [], null, $t0);
                return ['errores' => 1, 'motivo' => $e->getMessage()];
            }

            $response = $this->client->request('PUT', "/v1/products/{$producto->siigo_id}", $payload);

            // B2-A3 · update_not_allowed en agregado también.
            if ($response->failed() && $this->esUpdateNotAllowed($response->json())) {
                $this->log($producto, 'desactivar-agregado', 'ignorado', $response->status(),
                    'update_not_allowed (producto con cierre contable)', $payload, $response->json(), $t0);
                // No cuenta ni como éxito ni como error · ya no re-loggea fallido en cada tick.
            } elseif ($response->failed()) {
                $this->log($producto, 'desactivar-agregado', 'fallido', $response->status(),
                    'PUT active:false falló', $payload, $response->json(), $t0);
                $errores++;
            } else {
                $producto->forceFill(['siigo_sync_at' => now()])->save();
                $desactivados = 1;
            }
        }

        // M6 · siempre loggear resumen, incluso si desactivados=0.
        $this->log($producto, 'desactivar', $errores === 0 ? 'exitoso' : 'parcial', 200,
            "desactivados={$desactivados} errores={$errores} sin_siigo_id={$sinSiigoId}",
            ['modo' => $this->esGranular($producto) ? 'granular' : 'agregado'],
            ['variantes' => $detalle], $t0);

        return compact('desactivados', 'errores', 'sinSiigoId', 'detalle');
    }

    private function esGranular(Producto $p): bool
    {
        return is_null($p->desglose_stock)
            ? (bool) config('siigo.desglose_default', false)
            : (bool) $p->desglose_stock;
    }

    /** B2-A3 · idem que en Actualizar · SIIGO code `update_not_allowed`. */
    private function esUpdateNotAllowed(?array $body): bool
    {
        if (! $body) return false;
        foreach ($body['Errors'] ?? [] as $e) {
            if (($e['Code'] ?? null) === 'update_not_allowed') return true;
        }
        return false;
    }

    private function log(Producto $p, string $accion, string $estado, int $httpStatus, string $mensaje,
                          array $payload = [], ?array $responseBody = null, ?float $t0 = null, ?int $varianteId = null): void
    {
        SiigoSyncLog::create([
            'recurso' => 'productos',
            'estado' => $estado,
            'nuevos' => 0,
            'actualizados' => $estado === 'exitoso' ? 1 : 0,
            'errores' => $estado === 'fallido' ? 1 : 0,
            'duracion_ms' => $t0 ? (int) round((microtime(true) - $t0) * 1000) : 0,
            'mensaje' => "[{$accion}] p={$p->referencia}".($varianteId ? " v={$varianteId}" : '')." http={$httpStatus} · {$mensaje}",
            'detalle' => [
                'accion' => $accion,
                'producto_id' => $p->id,
                'variante_id' => $varianteId,
                'http_status' => $httpStatus,
                'payload' => $payload,
                // B4-M3 · sanitizar antes de persistir.
                'response_body' => $this->client->sanitizarRespuestaArray($responseBody),
            ],
            'user_id' => auth()->id(),
        ]);
    }
}
