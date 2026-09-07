<?php

namespace App\Modules\Dropi\Clients;

use App\Modules\Dropi\DTOs\ItemDropiDTO;
use App\Modules\Dropi\DTOs\PagoWalletDTO;
use App\Modules\Dropi\DTOs\PedidoDropiDTO;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Driver mock — lee fixtures locales para desarrollar sin la API real de Dropi.
 * §26 diseño Dropi: la solicitud a Dropi está enviada y sin respuesta.
 * Nos permite construir Actions, Jobs y UI ahora mismo con datos realistas.
 */
class DropiMockClient implements DropiClientInterface
{
    public function __construct(protected string $fixturesPath) {}

    public function driver(): string
    {
        return 'mock';
    }

    public function pedidosDesde(CarbonImmutable $desde): Collection
    {
        return collect($this->leerJson('pedidos.json'))
            ->map(fn (array $p) => $this->armarPedido($p))
            ->filter(fn (PedidoDropiDTO $p) => $p->creadoAt->greaterThanOrEqualTo($desde))
            ->values();
    }

    public function consultarGuia(string $guia): ?PedidoDropiDTO
    {
        $encontrado = collect($this->leerJson('pedidos.json'))
            ->firstWhere('guia', $guia);

        return $encontrado ? $this->armarPedido($encontrado) : null;
    }

    public function walletDesde(CarbonImmutable $desde): Collection
    {
        return collect($this->leerJson('wallet.json'))
            ->map(fn (array $m, int $idx) => new PagoWalletDTO(
                tipo: $m['tipo'],
                monto: (float) $m['monto'],
                fecha: CarbonImmutable::parse($m['fecha']),
                guia: $m['guia'] ?? null,
                categoria: $m['categoria'] ?? null,
                raw: $m,
                dropiMovimientoId: $m['id'] ?? ('MOCK-' . md5(json_encode($m) . $idx)),
            ))
            ->filter(fn (PagoWalletDTO $m) => $m->fecha->greaterThanOrEqualTo($desde))
            ->values();
    }

    protected function armarPedido(array $p): PedidoDropiDTO
    {
        $items = collect($p['items'] ?? [])->map(fn (array $it) => new ItemDropiDTO(
            skuDropi: $it['sku'],
            cantidad: (int) $it['cantidad'],
            precioProveedorUnit: (float) $it['precio_proveedor_unit'],
        ))->all();

        return new PedidoDropiDTO(
            guia: $p['guia'],
            dropiOrdenId: $p['orden_id'],
            transportadora: $p['transportadora'] ?? null,
            tienda: $p['tienda'] ?? null,
            vendedorNombre: $p['vendedor']['nombre'] ?? null,
            vendedorIdentificacion: $p['vendedor']['identificacion'] ?? null,
            clienteNombre: $p['cliente']['nombre'],
            clienteDoc: $p['cliente']['documento'] ?? null,
            clienteTelefono: $p['cliente']['telefono'] ?? null,
            clienteDireccion: $p['cliente']['direccion'] ?? null,
            clienteCiudad: $p['cliente']['ciudad'] ?? null,
            clienteDepto: $p['cliente']['depto'] ?? null,
            estadoDropi: $p['estado'],
            montoEsperadoProveedor: (float) $p['valores']['precio_proveedor_total'],
            montoClienteFinal: isset($p['valores']['cliente_final_total']) ? (float) $p['valores']['cliente_final_total'] : null,
            gananciaVendedor: isset($p['valores']['ganancia_vendedor']) ? (float) $p['valores']['ganancia_vendedor'] : null,
            fleteTransportadora: isset($p['valores']['flete_transportadora']) ? (float) $p['valores']['flete_transportadora'] : null,
            items: $items,
            creadoAt: CarbonImmutable::parse($p['creado_at']),
            despachadoAt: isset($p['despachado_at']) ? CarbonImmutable::parse($p['despachado_at']) : null,
            entregadoAt: isset($p['entregado_at']) ? CarbonImmutable::parse($p['entregado_at']) : null,
            pagadoAt: isset($p['pagado_at']) ? CarbonImmutable::parse($p['pagado_at']) : null,
            devueltoAt: isset($p['devuelto_at']) ? CarbonImmutable::parse($p['devuelto_at']) : null,
            raw: $p,
        );
    }

    protected function leerJson(string $archivo): array
    {
        $path = rtrim($this->fixturesPath, '/\\') . DIRECTORY_SEPARATOR . $archivo;

        if (! File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true) ?? [];
    }
}
