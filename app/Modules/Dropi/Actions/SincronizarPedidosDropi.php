<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Clients\DropiClientInterface;
use App\Modules\Dropi\DTOs\PedidoDropiDTO;
use App\Modules\Dropi\Enums\EstadoCorte;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
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
        // M4 · sync incremental. Si el caller no fija $desde, leemos el
        // last_sync_at del recurso 'pedidos' con un solape de 30 min para
        // absorber reintentos y latencia; si nunca sincronizó, arrancamos
        // hace 24 h.
        if ($desde === null) {
            $last = \Illuminate\Support\Facades\DB::table('dropi_sync_estado')
                ->where('recurso', 'pedidos')
                ->value('last_sync_at');
            $desde = $last
                ? CarbonImmutable::parse($last)->subMinutes(30)
                : CarbonImmutable::now()->subDay();
        }

        $ejecutadoEn = CarbonImmutable::now();
        $pedidos = $this->client->pedidosDesde($desde);
        $totalProcesados = $pedidos->count();

        $nuevos = 0;
        $actualizados = 0;
        $pendientesInv = 0;
        $errores = 0;

        // Re-audit DR-α (FUNC-M4) · procesar por chunks de 200 y liberar
        //   memoria entre lotes. Antes el foreach lineal sobre Collection
        //   de 5000+ pedidos + cada iteración con transacción + Eloquent
        //   hidratando pedido completo → memoria crecía hasta OOM.
        //
        //   Cambio raíz definitivo (pendiente): que `pedidosDesde()` retorne
        //   `LazyCollection` con cursor de streaming HTTP. Esto requiere
        //   modificar `DropiClientInterface` + Api + Mock. Por ahora chunks.
        foreach ($pedidos->chunk(200) as $lote) {
            foreach ($lote as $dto) {
                // H7 func · try/catch por pedido — un DTO tóxico NO puede bloquear
                // el sync completo. Log y sigue.
                try {
                    $resultado = DB::transaction(fn () => $this->guardarPedido($dto));
                } catch (\Throwable $e) {
                    $errores++;
                    \Illuminate\Support\Facades\Log::error('dropi.sync.pedido_error', [
                        'guia' => $dto->guia ?? null, 'msg' => $e->getMessage(),
                    ]);
                    continue;
                }

                match ($resultado) {
                    'nuevo' => $nuevos++,
                    'actualizado' => $actualizados++,
                    default => null,
                };

                if ($resultado === 'nuevo' || $resultado === 'actualizado') {
                    // Consulta liviana (sólo estado) para no re-hidratar el pedido.
                    $estado = DropiPedido::where('guia', $dto->guia)->value('estado');
                    if ($estado === 'pendiente_inventario') {
                        $pendientesInv++;
                    }
                }
            }
            // Liberar memoria entre chunks.
            unset($lote);
            gc_collect_cycles();
        }
        unset($pedidos);

        // M4 · dejar high-water-mark.
        // Re-audit N7 seg · race-safe: usar INSERT/UPDATE con GREATEST() para que
        // dos syncs concurrentes elijan siempre el timestamp mayor y no se pisen.
        \Illuminate\Support\Facades\DB::statement(
            "INSERT INTO dropi_sync_estado (recurso, last_sync_at, created_at, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                last_sync_at = GREATEST(last_sync_at, VALUES(last_sync_at)),
                updated_at = VALUES(updated_at)",
            ['pedidos', $ejecutadoEn->toDateTimeString(), now(), now()],
        );

        return [
            'procesados' => $totalProcesados,
            'nuevos' => $nuevos,
            'actualizados' => $actualizados,
            'pendientes_inv' => $pendientesInv,
            'errores' => $errores,
            'driver' => $this->client->driver(),
            'desde' => $desde->toIso8601String(),
        ];
    }

    protected function guardarPedido(PedidoDropiDTO $dto): string
    {
        // P7 · Normalizar guía (trim + upper) para blindar dedup.
        $guia = strtoupper(trim((string) $dto->guia));
        if ($guia === '') {
            return 'rechazado'; // guía vacía nunca entra
        }

        // S8 · sanitizar payload malformado desde la API externa.
        // Montos negativos, cantidades <= 0 en items → rechazamos el pedido entero.
        if ((float) $dto->montoEsperadoProveedor < 0) return 'rechazado';
        foreach ($dto->items as $it) {
            if ((int) $it->cantidad <= 0 || (float) $it->precioProveedorUnit < 0) {
                return 'rechazado';
            }
        }

        // M1 · cliente_nombre es NOT NULL en BD; si el payload trae null, usar
        // sentinel para no reventar el sync completo del lote.
        $clienteNombre = trim((string) ($dto->clienteNombre ?? ''));
        if ($clienteNombre === '') $clienteNombre = 'SIN NOMBRE';

        $corte = AsignarCorteACarga::run($dto->creadoAt);
        $existente = DropiPedido::where('guia', $guia)->first();

        // Re-audit DR-α (FUNC-C1) · antes: `$estadoInicial = $existente
        //   ? $existente->estado : porSku(...)` — para existente = estado
        //   actual → la comparación `$estadoInicial !== $existente->estado`
        //   (línea ~177) SIEMPRE era falsa → el estado Dropi NUNCA se
        //   sincronizaba. Si Dropi marcaba Entregado/Devuelto/Pagado, el
        //   pedido quedaba pegado en Alistando/Despachado y Aracely facturaba mal.
        //
        //   Fix: mapear `$dto->estadoDropi` (raw string) a EstadoPedidoDropi
        //   como fuente de verdad para existentes. Sólo cae a `porSku()` en
        //   creación cuando el DTO no trae estado interpretable.
        $itemsPlano = array_map(fn ($it) => ['sku' => $it->skuDropi, 'cantidad' => $it->cantidad], $dto->items);
        $estadoDesdeDropi = self::mapearEstadoDropi($dto->estadoDropi);
        $estadoInicial = $existente
            ? ($estadoDesdeDropi ?? $existente->estado)
            : ($estadoDesdeDropi ?? $this->resolverEstado->porSku($itemsPlano));

        $data = [
            'corte_id' => $corte->id,
            'dropi_orden_id' => $dto->dropiOrdenId,
            'transportadora' => $dto->transportadora,
            'tienda' => $dto->tienda,
            'vendedor_nombre' => $dto->vendedorNombre,
            'vendedor_identificacion' => $dto->vendedorIdentificacion,
            'cliente_nombre' => $clienteNombre,
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
            // Campos NO sensibles al estado: cliente/monto/fechas.
            $sinEstado = array_diff_key($data, ['estado' => true]);

            // A7 · fechas del payload sólo pisan si son MÁS recientes.
            foreach (['despachado_at', 'entregado_at', 'devuelto_at', 'pagado_at'] as $f) {
                $incoming = $sinEstado[$f] ?? null;
                if ($incoming && $existente->{$f} && $existente->{$f}->gt($incoming)) {
                    unset($sinEstado[$f]);
                }
            }

            $existente->fill($sinEstado)->save();

            // C6 · si el estado del payload cambia, transicionar vía state-machine
            // (fuente=api). Escribe bitácora automática y dispara side-effects.
            if ($estadoInicial !== $existente->estado) {
                try {
                    $existente->transicionar(
                        $estadoInicial,
                        'api',
                        null,
                        ['origen' => 'sync', 'raw_estado_dropi' => $dto->estadoDropi ?? null],
                    );
                } catch (\Throwable $e) {
                    // H5 datos · si la máquina rechaza (corte cerrado + estado no permitido),
                    // logueamos para trazabilidad — antes se tragaba silencio.
                    \Illuminate\Support\Facades\Log::warning('dropi.sync.transicion_bloqueada', [
                        'guia' => $existente->guia,
                        'de' => $existente->estado instanceof \App\Modules\Dropi\Enums\EstadoPedidoDropi
                            ? $existente->estado->value : (string) $existente->estado,
                        'a' => $estadoInicial->value,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }

            $this->sincronizarItems($existente, $dto);

            return 'actualizado';
        }

        // Re-audit DR-β · con $guarded, `estado` no pasa por fill. Se crea
        //   sin estado y se asigna por propiedad.
        $sinEstado = array_diff_key(array_merge($data, ['guia' => $guia]), ['estado' => true]);
        $pedido = DropiPedido::create($sinEstado);
        $pedido->estado = $estadoInicial;
        $pedido->save();

        $this->sincronizarItems($pedido, $dto);

        DropiEstadoBitacora::create([
            'pedido_id' => $pedido->id,
            'estado_desde' => null,
            'estado_hasta' => $estadoInicial->value,
            'fuente' => 'api',
            'payload' => $dto->raw,
            'user_id' => auth()->id(),
        ]);

        return 'nuevo';
    }

    /**
     * Re-audit DR-α (FUNC-C1) · mapper string Dropi → EstadoPedidoDropi.
     *
     *   Los estados que devuelve la API de Dropi son strings arbitrarios que
     *   dependen de la versión de su backend. Este mapper cubre los sinónimos
     *   comunes; los que no matcheen retornan null y el llamador decide
     *   (fallback a porSku o mantener actual).
     */
    public static function mapearEstadoDropi(?string $raw): ?EstadoPedidoDropi
    {
        if (! $raw) return null;
        $norm = strtolower(trim($raw));
        return match ($norm) {
            'pending', 'nuevo', 'pendiente'                => EstadoPedidoDropi::Pending,
            'preparando', 'alistando', 'in_preparation'    => EstadoPedidoDropi::Alistando,
            'empacado', 'packed'                           => EstadoPedidoDropi::Empacado,
            'despachado', 'shipped', 'in_transit', 'en_transito' => EstadoPedidoDropi::Despachado,
            'entregado', 'delivered', 'completed'          => EstadoPedidoDropi::Entregado,
            'devolucion', 'devolucion_en_camino', 'return_in_transit' => EstadoPedidoDropi::DevolucionEnCamino,
            'devuelto', 'returned'                          => EstadoPedidoDropi::Devuelto,
            'pagado', 'paid', 'settled'                     => EstadoPedidoDropi::Pagado,
            'cancelado', 'canceled', 'cancelled', 'cancelado_dropi' => EstadoPedidoDropi::CanceladoDropi,
            'cancelado_gb', 'canceled_by_seller'            => EstadoPedidoDropi::CanceladoGb,
            default => null,
        };
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

        // Raíz A (H1 datos) · los contadores del corte son responsabilidad EXCLUSIVA
        // del listener RecalcularContadoresCorte (evento PedidoDropiTransicionado).
        // Antes este método sobrescribía pedidos_despachados con criterio distinto
        // (solo estado='despachado') pisando el cálculo correcto del listener
        // (IN despachado,entregado,pagado). Eliminado — el listener corre en cada
        // transición api/sistema/manual y mantiene la coherencia.
    }
}
