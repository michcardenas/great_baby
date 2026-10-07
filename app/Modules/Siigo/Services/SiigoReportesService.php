<?php

namespace App\Modules\Siigo\Services;

use App\Modules\Siigo\Clients\SiigoClient;
use RuntimeException;

/**
 * Reportes que vienen DE SIIGO, no del ERP.
 *
 * Los 9 reportes de Contabilidad del ERP leen `movimientos_contables` local:
 * sirven para operar, pero no prueban nada — si un asiento nunca llegó a SIIGO
 * el reporte local igual lo muestra. Estos métodos consultan la contabilidad
 * real para poder contrastar y para que Aracely baje el balance oficial.
 */
class SiigoReportesService
{
    public function __construct(private readonly SiigoClient $cliente) {}

    /**
     * Balance de prueba oficial. SIIGO lo genera de forma asíncrona y responde
     * con un id y la URL de un Excel ya construido.
     *
     * @return array{id: string, url: string}
     */
    public function balanceDePrueba(
        int $anio,
        int $mesInicio = 1,
        int $mesFin = 13,          // 13 = incluye el mes de cierre
        string $cuentaInicio = '11050501',
        string $cuentaFin = '53053502',
        bool $incluirDiferenciaImpuestos = false,
    ): array {
        return $this->reporteExcel('/v1/test-balance-report', [
            'account_start' => $cuentaInicio,
            'account_end' => $cuentaFin,
            'year' => $anio,
            'month_start' => $mesInicio,
            'month_end' => $mesFin,
            'includes_tax_difference' => $incluirDiferenciaImpuestos,
        ]);
    }

    /**
     * Mismo balance pero acotado a un tercero (NIT). Sirve para auditar la
     * cuenta de un cliente o proveedor puntual contra lo que dice el ERP.
     *
     * @return array{id: string, url: string}
     */
    public function balancePorTercero(
        string $identificacion,
        int $anio,
        int $mesInicio = 1,
        int $mesFin = 13,
        int $sucursal = 0,
        string $cuentaInicio = '11050501',
        string $cuentaFin = '53053502',
    ): array {
        return $this->reporteExcel('/v1/test-balance-report-by-thirdparty', [
            'account_start' => $cuentaInicio,
            'account_end' => $cuentaFin,
            'year' => $anio,
            'month_start' => $mesInicio,
            'month_end' => $mesFin,
            'includes_tax_difference' => true,
            'customer' => [
                'identification' => $identificacion,
                'branch_office' => $sucursal,
            ],
        ]);
    }

    /**
     * Cuentas por pagar vigentes en SIIGO: documento, vencimiento, saldo y
     * proveedor. Es la contraparte real del módulo de pagos a proveedor.
     *
     * @return list<array{prefijo: string, consecutivo: int, cuota: int, vence: ?string, saldo: float, proveedor: string, nit: string}>
     */
    public function cuentasPorPagar(): array
    {
        $r = $this->cliente->request('GET', '/v1/accounts-payable');
        if ($r->failed()) {
            throw new RuntimeException(
                'SIIGO no entregó las cuentas por pagar (HTTP '.$r->status().').'
            );
        }

        $json = $r->json();
        $filas = $json['results'] ?? (is_array($json) ? $json : []);

        return collect($filas)->map(fn (array $x) => [
            'prefijo' => (string) ($x['due']['prefix'] ?? ''),
            'consecutivo' => (int) ($x['due']['consecutive'] ?? 0),
            'cuota' => (int) ($x['due']['quote'] ?? 1),
            'vence' => $x['due']['date'] ?? null,
            'saldo' => (float) ($x['due']['balance'] ?? 0),
            'proveedor' => (string) ($x['provider']['name'] ?? ''),
            'nit' => (string) ($x['provider']['identification'] ?? ''),
        ])->values()->all();
    }

    /**
     * Los dos balances responden igual: `[id, urlDelExcel]` en un array plano.
     *
     * @return array{id: string, url: string}
     */
    private function reporteExcel(string $endpoint, array $payload): array
    {
        $r = $this->cliente->request('POST', $endpoint, $payload);
        if ($r->failed()) {
            $msg = (string) ($r->json('Errors.0.Message')
                ?? $r->json('errors.0.message')
                ?? 'HTTP '.$r->status());
            throw new RuntimeException("SIIGO rechazó el reporte: {$msg}");
        }

        $datos = (array) $r->json();
        $url = collect($datos)->first(fn ($v) => is_string($v) && str_starts_with($v, 'http'));
        $id = collect($datos)->first(fn ($v) => is_string($v) && ! str_starts_with($v, 'http'));

        if (! $url) {
            throw new RuntimeException('SIIGO respondió sin la URL del reporte.');
        }

        return ['id' => (string) $id, 'url' => (string) $url];
    }
}
