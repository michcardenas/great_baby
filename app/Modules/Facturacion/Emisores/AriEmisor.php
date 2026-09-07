<?php

namespace App\Modules\Facturacion\Emisores;

use App\Support\Contracts\EmisorDocumentoFiscal;
use Illuminate\Support\Facades\Log;

/**
 * Emisor ARI — stub inicial.
 * Cuando lleguen las credenciales y docs de la API de ARI (§26), esta clase
 * se completa sin tocar el resto del sistema (todo depende del interface).
 */
class AriEmisor implements EmisorDocumentoFiscal
{
    public function __construct(protected string $apiUrl = '', protected string $apiKey = '') {}

    public function driver(): string
    {
        return $this->apiKey ? 'ari' : 'ari-mock';
    }

    public function emitirFactura(array $encabezado, array $lineas): array
    {
        if (! $this->apiKey) {
            $id = 'ARI-MOCK-' . strtoupper(substr(md5(json_encode($encabezado) . microtime()), 0, 10));
            Log::info('[ARI mock] Factura emitida', ['id' => $id, 'total' => array_sum(array_column($lineas, 'total'))]);
            return ['id_externo' => $id, 'cufe' => str_repeat('0', 96), 'estado_dian' => 'aceptado_mock'];
        }
        // TODO: HTTP real cuando llegue documentación oficial
        throw new \LogicException('AriEmisor::emitirFactura() real aún no implementado.');
    }

    public function emitirNotaCredito(string $facturaOrigenId, array $encabezado, array $lineas): array
    {
        if (! $this->apiKey) {
            $id = 'NC-ARI-MOCK-' . strtoupper(substr(md5($facturaOrigenId . microtime()), 0, 10));
            Log::info('[ARI mock] NC emitida', ['id' => $id, 'factura_origen' => $facturaOrigenId]);
            return ['id_externo' => $id, 'estado_dian' => 'aceptado_mock'];
        }
        throw new \LogicException('AriEmisor::emitirNotaCredito() real aún no implementado.');
    }

    public function emitirLote(array $facturas): array
    {
        $resultados = [];
        foreach ($facturas as $f) {
            $resultados[] = $this->emitirFactura($f['encabezado'] ?? [], $f['lineas'] ?? []);
        }
        return [
            'lote_id' => 'LOTE-' . now()->format('YmdHis'),
            'resultados' => $resultados,
        ];
    }

    public function consultarEstado(string $idExterno): array
    {
        return ['id_externo' => $idExterno, 'estado_dian' => 'aceptado_mock'];
    }
}
