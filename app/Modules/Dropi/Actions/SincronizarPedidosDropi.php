<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Clients\DropiClientInterface;
use App\Modules\Dropi\DTOs\PedidoDropiDTO;
use App\Modules\Dropi\Enums\EstadoCorte;
use App\Modules\Dropi\Models\DropiEstadoBitacora;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\DropiPedidoItem;
use App\Modules\Dropi\Models\ProductoVariante;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * §2, §5 Diseño Dropi — Sincroniza pedidos desde Dropi hacia el estado integral.
 * Idempotente por guía: si ya existe, no la duplica.
 * Cada creación o cambio de estado queda registrado en bitácora (fuente=api|manual|sistema).
 */
class SincronizarPedidosDropi
{
    use AsAction;

    public function __construct(
        protected DropiClientInterface $client,
        protected ResolverEstadoInicial $resolverEstado,
    ) {}

    /**
     * @return array{procesados:int, nuevos:int, actualizados:int, pendientes_inv:int, driver:string}
     */
    public function handle(?CarbonImmutable $desde = null): array
    {
        $desde ??= CarbonImmutable::now()->subDay();

        $pedidos = $this->client->pedidosDesde($desde);

        $nuevos = 0;
        $actualizados = 0;
        $pendientesInv = 0;

        foreach ($pedidos as $dto) {
            $resultado = DB::transaction(fn () => $this->guardarPedido($dto));

            match ($resultado) {
                'nuevo' => $nuevos++,
                'actualizado' => $actualizados++,
                default => null,
            };

            if ($resultado === 'nuevo' || $resultado === 'actualizado') {
                $pedido = DropiPedido::where('guia', $dto->guia)->first();
                if ($pedido && $pedido->estado->value === 'pendiente_inventario') {
                    $pendientesInv++;
                }
            }
        }

        return [
            'procesados' => $pedidos->count(),
            'nuevos' => $nuevos,
            'actualizados' => $actualizados,
            'pendientes_inv' => $pendientesInv,
            'driver' => $this->client->driver(),
        ];
    }

    protected function guardarPedido(PedidoDropiDTO $dto): string
    {
        $corte = AsignarCorteACarga::run($dto->creadoAt);
        $existente = DropiPedido::where('guia', $dto->guia)->first();

        // Estado inicial según stock disponible (solo la primera vez).
        $itemsPlano = array_map(fn ($it) => ['sku' => $it->skuDropi, 'cantidad' => $it->cantidad], $dto->items);
        $estadoInicial = $existente
            ? $existente->estado
            : $this->resolverEstado->porSku($itemsPlano);

        $data = [
            'corte_id' => $corte->id,
            'dropi_orden_id' => $dto->dropiOrdenId,
            'transportadora' => $dto->transportadora,
            'tienda' => $dto->tienda,
            'vendedor_nombre' => $dto->vendedorNombre,
            'vendedor_identificacion' => $dto->vendedorIdentificacion,
            'cliente_nombre' => $dto->clienteNombre,
            'cliente_doc' => $dto->clienteDoc,
            'cliente_telefono' => $dto->clienteTelefono,
            'cliente_direccion' => $dto->clienteDireccion,
            'cliente_ciudad' => $dto->clienteCiudad,
            'cliente_depto' => $dto->clienteDepto,
            'estado' => $estadoInicial,
            'monto_esperado_proveedor' => $dto->montoEsperadoProveedor,
            'monto_cliente_final' => $dto->montoClienteFinal,
            'ganancia_vendedor' => $dto->gananciaVendedor,
            'flete_transportadora' => $dto->fleteTransportadora,
            'despachado_at' => $dto->despachadoAt,
            'entregado_at' => $dto->entregadoAt,
            'devuelto_at' => $dto->devueltoAt,
            'pagado_at' => $dto->pagadoAt,
        ];

        if ($existente) {
            $existente->fill($data)->save();
            $this->sincronizarItems($existente, $dto);

            return 'actualizado';
        }

        $pedido = DropiPedido::create(array_merge($data, ['guia' => $dto->guia]));
        $this->sincronizarItems($pedido, $dto);

        DropiEstadoBitacora::create([
            'pedido_id' => $pedido->id,
            'estado_desde' => null,
            'estado_hasta' => $estadoInicial->value,
            'fuente' => 'api',
            'payload' => $dto->raw,
            'user_id' => auth()->id(), // quien disparó el sync (null si vino de cron)
        ]);

        return 'nuevo';
    }

    protected function sincronizarItems(DropiPedido $pedido, PedidoDropiDTO $dto): void
    {
        foreach ($dto->items as $item) {
            $variante = ProductoVariante::where('codigo_barras', $item->skuDropi)->first();

            DropiPedidoItem::updateOrCreate(
                ['pedido_id' => $pedido->id, 'sku_dropi' => $item->skuDropi],
                [
                    'variante_id' => $variante?->id,
                    'cantidad' => $item->cantidad,
                    'precio_proveedor_unit' => $item->precioProveedorUnit,
                ],
            );
        }

        $pedido->corte->update([
            'pedidos_totales' => $pedido->corte->pedidos()->count(),
            'pedidos_pendientes_inv' => $pedido->corte->pedidos()->where('estado', 'pendiente_inventario')->count(),
            'pedidos_despachados' => $pedido->corte->pedidos()->where('estado', 'despachado')->count(),
        ]);
    }
}
