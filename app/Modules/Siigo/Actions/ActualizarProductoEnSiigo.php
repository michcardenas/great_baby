<?php

namespace App\Modules\Siigo\Actions;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoSyncLog;
use App\Modules\Siigo\Support\ProductoPayloadBuilder;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * PUT /v1/products/{siigo_id} · actualiza el producto o cada variante en Siigo.
 * Si un item local no tiene siigo_id todavía, delega la creación de ESE ITEM
 * (no el producto entero) a CrearProductoEnSiigo::crearVariante() → evita el
 * bug N² del auditor (C1).
 *
 * Fixes auditoría:
 *   C1 · Delegación a nivel variante (no producto entero).
 *   A2 · duracion_ms medido con microtime.
 *   A4 · `update_not_allowed` capturado como 'skipped', no explota Observer.
 *   A5 · desglose_stock null → default explícito.
 *   A6 · setRelation('producto') antes de iterar variantes.
 *   M5 · Log guarda response_body completo.
 */
class ActualizarProductoEnSiigo
{
    use AsAction;

    public function __construct(
        private readonly SiigoClient $client,
        private readonly ProductoPayloadBuilder $builder,
        private readonly CrearProductoEnSiigo $creador,
    ) {}

    public function handle(Producto $producto): array
    {
        // B3-P1 · eager `variantes.preciosVigentes` (antes N+1 en granular).
        $producto->loadMissing([
            'variantes.preciosVigentes',
            'marca',
            'categoriaMaestra',
            'impuesto',
            'unidadMedida',
        ]);

        return $this->esGranular($producto)
            ? $this->actualizarGranular($producto)
            : $this->actualizarAgregado($producto);
    }

    private function esGranular(Producto $p): bool
    {
        return is_null($p->desglose_stock)
            ? (bool) config('siigo.desglose_default', false)
            : (bool) $p->desglose_stock;
    }

    private function actualizarAgregado(Producto $p): array
    {
        $t0 = microtime(true);

        if (! $p->siigo_id) {
            // Aún no existe en Siigo → crear
            return CrearProductoEnSiigo::run($p);
        }

        $payload = $this->builder->paraProducto($p);
        $response = $this->client->request('PUT', "/v1/products/{$p->siigo_id}", $payload);

        // A4 · `update_not_allowed` → skipped benigno
        if ($response->failed() && $this->esUpdateNotAllowed($response->json())) {
            $this->log($p, 'actualizar-agregado', 'ignorado', $response->status(),
                'update_not_allowed (siigo_id ya tiene movimientos)', $payload, $response->json(), $t0);
            return ['skipped' => true, 'motivo' => 'update_not_allowed'];
        }

        if ($response->failed()) {
            $this->log($p, 'actualizar-agregado', 'fallido', $response->status(),
                'PUT /v1/products falló', $payload, $response->json(), $t0);
            throw new RuntimeException("SIIGO PUT /v1/products/{$p->siigo_id} falló · status {$response->status()} · producto {$p->referencia}");
        }

        // C5 · validar que el body tenga id · SIIGO puede responder 200 con
        // body vacío o {} en fallos raros · sin este check marcábamos
        // sync_at=now() sin confirmación real.
        $data = $response->json();
        if (! is_array($data) || ! isset($data['id'])) {
            $this->log($p, 'actualizar-agregado', 'fallido', $response->status(),
                'respuesta 200 sin id', $payload, $data, $t0);
            throw new RuntimeException("SIIGO PUT 200 pero respuesta sin id · producto {$p->referencia}");
        }

        $p->forceFill(['siigo_sync_at' => now()])->save();
        $this->log($p, 'actualizar-agregado', 'exitoso', $response->status(), 'OK',
            $payload, $data, $t0);
        return ['actualizados' => 1, 'siigo_id' => $p->siigo_id];
    }

    private function actualizarGranular(Producto $p): array
    {
        $t0 = microtime(true);
        $actualizados = 0; $errores = 0; $creados = 0; $skipped = 0;
        $detalle = [];

        foreach ($p->variantes as $v) {
            // A6 · pre-cargar relación producto para evitar N+1
            $v->setRelation('producto', $p);

            if (! $v->siigo_id) {
                // C1 · crear SOLO esta variante, no el producto entero.
                $r = $this->creador->crearVariante($v);
                if (! empty($r['siigo_id'])) $creados++;
                else $errores++;
                $detalle[] = ['variante' => $v->codigo_barras, 'resultado' => $r];
                continue;
            }

            try {
                $payload = $this->builder->paraVariante($v);
            } catch (\Throwable $e) {
                $errores++;
                $detalle[] = ['variante' => $v->codigo_barras, 'error' => $e->getMessage()];
                $this->log($p, 'actualizar-variante', 'fallido', 0, 'builder: '.$e->getMessage(),
                    [], null, $t0, $v->id);
                continue;
            }

            $response = $this->client->request('PUT', "/v1/products/{$v->siigo_id}", $payload);

            // A4 · update_not_allowed → skipped
            if ($response->failed() && $this->esUpdateNotAllowed($response->json())) {
                $skipped++;
                $this->log($p, 'actualizar-variante', 'ignorado', $response->status(),
                    'update_not_allowed', $payload, $response->json(), $t0, $v->id);
                continue;
            }

            if ($response->failed()) {
                $errores++;
                $detalle[] = ['variante' => $v->codigo_barras, 'error' => $response->status()];
                $this->log($p, 'actualizar-variante', 'fallido', $response->status(),
                    'PUT falló', $payload, $response->json(), $t0, $v->id);
                continue;
            }

            // C5 · validar body 200 con id para variante también.
            $data = $response->json();
            if (! is_array($data) || ! isset($data['id'])) {
                $errores++;
                $detalle[] = ['variante' => $v->codigo_barras, 'error' => 'respuesta sin id'];
                $this->log($p, 'actualizar-variante', 'fallido', $response->status(),
                    '200 sin id', $payload, $data, $t0, $v->id);
                continue;
            }

            $v->forceFill(['siigo_sync_at' => now()])->save();
            $actualizados++;
        }

        // B4-A4 · marcar `siigo_sync_at` del padre con la fecha más antigua
        // de sus variantes exitosas · así reportes que miran producto.siigo_sync_at
        // reflejan cuándo se sincronizó por última vez el bloque más viejo.
        if ($actualizados > 0 || $creados > 0) {
            $minSync = $p->variantes
                ->whereNotNull('siigo_sync_at')
                ->min('siigo_sync_at');
            if ($minSync) {
                $p->forceFill(['siigo_sync_at' => $minSync])->save();
            }
        }

        $this->log($p, 'actualizar-granular', $errores === 0 ? 'exitoso' : 'parcial', 200,
            "actualizados={$actualizados} creados={$creados} skipped={$skipped} errores={$errores}",
            ['modo' => 'granular'], ['variantes' => $detalle], $t0);

        return compact('actualizados', 'creados', 'skipped', 'errores', 'detalle');
    }

    private function esUpdateNotAllowed(?array $body): bool
    {
        if (! $body) return false;
        $errores = $body['Errors'] ?? [];
        foreach ($errores as $e) {
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
