<?php

namespace App\Modules\Siigo\Actions;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoSyncLog;
use Illuminate\Http\Client\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Hard-delete en SIIGO (DELETE /v1/products/{uuid}).
 *
 * Flujo: intenta DELETE real. Si SIIGO responde `delete_not_allowed` (porque
 * el producto tiene movimientos contables), cae automáticamente al soft-delete
 * usando DesactivarProductoEnSiigo. Esta Action se dispara al `forceDeleted`
 * del Observer.
 */
class EliminarProductoEnSiigo
{
    use AsAction;

    public function __construct(
        private readonly SiigoClient $client,
        private readonly DesactivarProductoEnSiigo $fallback,
    ) {}

    public function handle(Producto $producto): array
    {
        $producto->loadMissing(['variantes']);
        $t0 = microtime(true);

        $borrados = 0;
        $desactivados = 0;
        $errores = 0;

        if ($this->esGranular($producto)) {
            foreach ($producto->variantes as $v) {
                if (! $v->siigo_id) continue;
                $r = $this->client->request('DELETE', "/v1/products/{$v->siigo_id}");
                $out = $this->procesarRespuesta($r);
                $borrados     += $out['borrado'] ? 1 : 0;
                $desactivados += $out['debe_desactivar'] ? 1 : 0;
                $errores      += $out['error'] ? 1 : 0;

                $this->log($producto, 'eliminar-variante', $out, $v->siigo_id, $v->id, $t0);
            }

            // Fallback a desactivar las que SIIGO rechazó borrar.
            if ($desactivados > 0) {
                $this->fallback->handle($producto);
            }
        } else {
            if (! $producto->siigo_id) {
                $this->log($producto, 'eliminar-agregado', ['nota' => 'sin siigo_id'], null, null, $t0);
                return ['skipped' => true];
            }
            $r = $this->client->request('DELETE', "/v1/products/{$producto->siigo_id}");
            $out = $this->procesarRespuesta($r);
            $borrados     = $out['borrado'] ? 1 : 0;
            $desactivados = $out['debe_desactivar'] ? 1 : 0;
            $errores      = $out['error'] ? 1 : 0;

            $this->log($producto, 'eliminar-agregado', $out, $producto->siigo_id, null, $t0);

            if ($out['debe_desactivar']) {
                $this->fallback->handle($producto);
            }
        }

        return compact('borrados', 'desactivados', 'errores');
    }

    /**
     * @return array{borrado:bool, debe_desactivar:bool, error:bool, nota:string, http:int}
     */
    private function procesarRespuesta(Response $r): array
    {
        $http = $r->status();
        if ($r->successful() || $http === 204) {
            return ['borrado' => true, 'debe_desactivar' => false, 'error' => false,
                    'nota' => "DELETE OK ({$http})", 'http' => $http];
        }
        $body = $r->json() ?? [];
        foreach (($body['Errors'] ?? []) as $e) {
            if (($e['Code'] ?? null) === 'delete_not_allowed') {
                return ['borrado' => false, 'debe_desactivar' => true, 'error' => false,
                        'nota' => 'delete_not_allowed → fallback a active:false', 'http' => $http];
            }
        }
        return ['borrado' => false, 'debe_desactivar' => false, 'error' => true,
                'nota' => 'DELETE falló http='.$http, 'http' => $http];
    }

    private function esGranular(Producto $p): bool
    {
        // RAÍZ · modo decidido por tener variantes, no por una config.
        return $p->variantes->isNotEmpty();
    }

    private function log(Producto $p, string $accion, array $out, ?string $siigoId, ?int $varId, float $t0): void
    {
        $estado = $out['error'] ?? false ? 'fallido' : (($out['borrado'] ?? false) ? 'exitoso' : 'ignorado');
        SiigoSyncLog::create([
            'recurso' => 'productos',
            'estado' => $estado,
            'nuevos' => 0,
            'actualizados' => ($out['borrado'] ?? false) ? 1 : 0,
            'errores' => ($out['error'] ?? false) ? 1 : 0,
            'duracion_ms' => (int) round((microtime(true) - $t0) * 1000),
            'mensaje' => "[{$accion}] p={$p->referencia}".($varId ? " v={$varId}" : '')." siigo={$siigoId} · ".($out['nota'] ?? ''),
            'detalle' => [
                'accion' => $accion,
                'producto_id' => $p->id,
                'variante_id' => $varId,
                'siigo_id' => $siigoId,
                'http' => $out['http'] ?? 0,
            ],
            'user_id' => auth()->id(),
        ]);
    }
}
