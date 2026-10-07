<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Modules\Cartera\Actions\ConsultarCredito;
use App\Modules\Catalogo\Models\PrecioVariante;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Portal\Models\PedidoCliente;
use App\Modules\Portal\Models\PedidoClienteItem;
use App\Services\SeguimientoVendedorService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * LOG-J1 · El vendedor arma pedido "a nombre de" un cliente.
 *
 * Los 8 vendedores de Jorge visitan comercios con muestras. Antes volvían
 * a la oficina y pedían a Aracely que levantara los pedidos. Esta pantalla
 * los deja levantar el pedido ellos mismos desde su celular/tablet con su
 * propia sesión del ERP, dejando el `vendedor_id` registrado para que
 * comisiones y gerencia sepan quién vendió qué.
 *
 * El pedido pasa por el mismo semáforo de cartera que LOG-J2 (si el
 * cliente tiene mora, el pedido queda retenido).
 */
class VendedorPedidoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            $u = $r->user();
            // Antes preguntaba `hasRole('Vendedor')`: el rol estaba escrito en
            // código y crear uno nuevo con ese acceso no servía de nada.
            abort_unless(
                \App\Auth\Permisos::puede($u, 'vendedor'),
                403,
                'Esta vista es para vendedores en terreno.'
            );
            return $next($r);
        })];
    }

    /**
     * LOG-J1 + Miracle port · Dashboard del vendedor con los KPIs del mes,
     *   comparativa con el periodo anterior, pedidos por estado (funnel),
     *   ranking de clientes y últimos pedidos levantados. Debajo del tablero
     *   queda el buscador para armar un pedido nuevo.
     */
    public function index(Request $r, SeguimientoVendedorService $sv): Response
    {
        $vendedorId = (int) $r->user()->id;
        // Aracely/Gerencia ven agregado (vendedor_id=null); cada Vendedor
        //   solo ve lo suyo. Mismo patrón que Miracle PanelVendedorController.
        $esSuper = $r->user()->esAracely();
        $vendedorScope = $esSuper ? null : $vendedorId;

        [$periodo, $ini, $fin, $iniAnt, $finAnt] = $sv->resolverPeriodo(
            $r->input('periodo'),
            $r->input('desde'),
            $r->input('hasta')
        );

        $comparativa = $sv->comparativaVendedor($vendedorScope, $ini, $fin, $iniAnt, $finAnt);
        $funnel      = $sv->pedidosPorEstado($vendedorScope, $ini, $fin);
        $ranking     = $sv->rankingClientes($vendedorScope, $ini, $fin, 10);
        $tendencia   = $sv->tendenciaDiaria($vendedorScope, 30);

        // Últimos 10 pedidos levantados por este vendedor (independiente del
        //   periodo seleccionado · es la bandeja de trabajo).
        $misPedidos = PedidoCliente::query()
            ->when(! $esSuper, fn ($q) => $q->where('vendedor_id', $vendedorId))
            ->with(['contacto:id,razon_social,nombre_completo'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'numero', 'contacto_id', 'estado', 'total', 'created_at', 'motivo_retencion'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'numero' => $p->numero,
                'cliente' => $p->contacto?->razon_social ?: $p->contacto?->nombre_completo,
                'estado' => $p->estado,
                'total' => (float) $p->total,
                'fecha' => $p->created_at?->format('Y-m-d H:i'),
                'motivo_retencion' => $p->motivo_retencion,
            ]);

        // Buscador de clientes para armar pedido nuevo · un vendedor sólo ve su
        //   cartera y las cuentas libres. Sin esto cualquiera de los 8 abría el
        //   cliente de un compañero y se quedaba la comisión.
        $clientes = Contacto::query()
            ->where('activo', true)
            ->when(! $esSuper, fn ($q) => $q->deVendedor($vendedorId))
            ->with('vendedor:id,name')
            ->when(! empty($r->input('q')), function ($q) use ($r) {
                $term = '%' . trim($r->input('q')) . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('razon_social', 'like', $term)
                      ->orWhere('nombre_completo', 'like', $term)
                      ->orWhere('numero_documento', 'like', $term)
                      ->orWhere('email', 'like', $term);
                });
            })
            ->whereNotNull('lista_precios_id')
            ->orderBy('razon_social')
            ->limit(20)
            ->get(['id', 'razon_social', 'nombre_completo', 'numero_documento', 'email',
                   'ciudad', 'lista_precios_id', 'telefono', 'vendedor_id'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'razon_social' => $c->razon_social,
                'nombre_completo' => $c->nombre_completo,
                'numero_documento' => $c->numero_documento,
                'email' => $c->email,
                'ciudad' => $c->ciudad,
                'telefono' => $c->telefono,
                'lista_precios_id' => $c->lista_precios_id,
                // Para que el vendedor distinga su cartera de las cuentas libres.
                'es_mio' => (int) $c->vendedor_id === $vendedorId,
                'libre' => $c->vendedor_id === null,
                'vendedor' => $c->vendedor?->name,
            ]);

        return Inertia::render('Vendedor/Panel', [
            'periodo' => $periodo,
            'rango' => [
                'inicio' => $ini->format('Y-m-d'),
                'fin' => $fin->format('Y-m-d'),
            ],
            'resumen' => $comparativa['actual'],
            'anterior' => $comparativa['anterior'],
            'variacion' => $comparativa['variacion'],
            'funnel' => $funnel,
            'ranking' => $ranking,
            'tendencia' => $tendencia,
            'mis_pedidos' => $misPedidos,
            'clientes' => $clientes,
            'q' => $r->input('q'),
            'es_super' => $esSuper,
        ]);
    }

    /** Miracle port · Ventas por cliente (ranking + última compra). */
    public function ventasPorCliente(Request $r, SeguimientoVendedorService $sv): Response
    {
        $u = $r->user();
        $scope = $u->esAracely() ? null : (int) $u->id;
        [$periodo, $ini, $fin] = $sv->resolverPeriodo($r->input('periodo'), $r->input('desde'), $r->input('hasta'));

        return Inertia::render('Vendedor/VentasPorCliente', [
            'periodo' => $periodo,
            'rango' => ['inicio' => $ini->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'clientes' => $sv->rankingClientes($scope, $ini, $fin, 50),
            'es_super' => $u->esAracely(),
        ]);
    }

    /** Miracle port · Contado vs crédito (desglose por tipo de pago en el periodo). */
    public function contadoCredito(Request $r, SeguimientoVendedorService $sv): Response
    {
        $u = $r->user();
        $scope = $u->esAracely() ? null : (int) $u->id;
        [$periodo, $ini, $fin] = $sv->resolverPeriodo($r->input('periodo'), $r->input('desde'), $r->input('hasta'));

        // Resumen contado/crédito desde FacturaVenta por tipo.
        $contado = \App\Modules\Cartera\Models\FacturaVenta::query()
            ->whereBetween('fecha_emision', [$ini->toDateString(), $fin->toDateString()])
            ->whereNotIn('estado', ['borrador', 'anulada'])
            ->where('tipo', 'contado')
            ->whereHas('origen', function ($q) use ($u, $scope) {
                if (! $u->esAracely()) {
                    $q->where('vendedor_id', $scope);
                }
            })
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto')
            ->first();

        $credito = \App\Modules\Cartera\Models\FacturaVenta::query()
            ->whereBetween('fecha_emision', [$ini->toDateString(), $fin->toDateString()])
            ->whereNotIn('estado', ['borrador', 'anulada'])
            ->where('tipo', 'credito')
            ->whereHas('origen', function ($q) use ($u, $scope) {
                if (! $u->esAracely()) {
                    $q->where('vendedor_id', $scope);
                }
            })
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto, COALESCE(SUM(saldo), 0) as saldo_pendiente')
            ->first();

        return Inertia::render('Vendedor/ContadoCredito', [
            'periodo' => $periodo,
            'rango' => ['inicio' => $ini->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'contado' => [
                'cantidad' => (int) ($contado->cantidad ?? 0),
                'monto' => (float) ($contado->monto ?? 0),
            ],
            'credito' => [
                'cantidad' => (int) ($credito->cantidad ?? 0),
                'monto' => (float) ($credito->monto ?? 0),
                'saldo_pendiente' => (float) ($credito->saldo_pendiente ?? 0),
            ],
            'es_super' => $u->esAracely(),
        ]);
    }

    /** Miracle port · Seguimiento (pendientes · por cobrar · últimos). */
    public function seguimiento(Request $r, SeguimientoVendedorService $sv): Response
    {
        $u = $r->user();
        $scope = $u->esAracely() ? null : (int) $u->id;
        $data = $sv->seguimientoPedidos($scope);

        return Inertia::render('Vendedor/Seguimiento', [
            'pendientes' => $data['pendientes'],
            'por_cobrar' => $data['por_cobrar'],
            'ultimos' => $data['ultimos'],
            'totales' => $data['totales'],
            'es_super' => $u->esAracely(),
        ]);
    }

    public function nuevo(Request $r, int $contactoId): Response
    {
        $cliente = Contacto::query()
            ->with('lista:id,nombre')
            ->where('id', $contactoId)
            ->whereNotNull('lista_precios_id')
            ->firstOrFail();

        $this->autorizarCuenta($cliente, $r);

        // Catálogo con el precio que corresponde al cliente — reutilizamos
        //   PrecioVariante · lista_id del cliente.
        $precios = PrecioVariante::query()
            ->where('lista_id', $cliente->lista_precios_id)
            ->where(function ($w) {
                $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', now()->toDateString());
            })
            ->pluck('precio', 'variante_id');

        $variantes = ProductoVariante::query()
            ->with(['producto' => fn ($q) => $q->select('id', 'referencia', 'nombre', 'impuesto_id')
                ->with('impuesto:id,porcentaje')])
            ->whereIn('id', $precios->keys())
            ->whereHas('producto', fn ($q) => $q->where('activo', true))
            ->orderBy('id')
            ->get(['id', 'producto_id', 'codigo_barras', 'color_nombre', 'talla'])
            ->map(fn ($v) => [
                'variante_id' => $v->id,
                'sku' => $v->codigo_barras ?: ($v->producto?->referencia . '-' . $v->id),
                'ref' => $v->producto?->referencia,
                'nombre' => $v->producto?->nombre,
                'color' => $v->color_nombre,
                'talla' => $v->talla,
                'precio' => (float) ($precios[$v->id] ?? 0),
                'iva_pct' => (float) ($v->producto?->impuesto?->porcentaje ?? 0),
            ])
            ->values();

        // Semáforo rápido · para que el vendedor sepa en terreno si el pedido
        //   del cliente va a quedar retenido antes de armarlo.
        $credito = ConsultarCredito::run($cliente->id);

        return Inertia::render('Vendedor/NuevoPedido', [
            'cliente' => [
                'id' => $cliente->id,
                'nombre' => $cliente->razon_social ?: $cliente->nombre_completo,
                'documento' => $cliente->numero_documento,
                'ciudad' => $cliente->ciudad,
                'lista' => $cliente->lista?->nombre,
                'telefono' => $cliente->telefono,
            ],
            'variantes' => $variantes,
            'credito' => [
                'tiene_mora_critica' => (bool) ($credito['tiene_mora_critica'] ?? false),
                'facturas_vencidas' => (int) ($credito['facturas_vencidas'] ?? 0),
                'saldo_cartera' => (float) ($credito['saldo_cartera'] ?? 0),
                'cupo' => (float) ($credito['cupo'] ?? 0),
                'dias_mora_max' => (int) ($credito['dias_mora_max'] ?? 0),
            ],
        ]);
    }

    public function confirmar(Request $r, int $contactoId): RedirectResponse
    {
        $data = $r->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.variante_id' => ['required', 'integer', 'exists:producto_variantes,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $cliente = Contacto::whereKey($contactoId)->whereNotNull('lista_precios_id')->firstOrFail();
        $this->autorizarCuenta($cliente, $r);

        $listaId = (int) $cliente->lista_precios_id;
        $vendedor = $r->user();

        $varianteIds = collect($data['items'])->pluck('variante_id')->unique();
        $precios = PrecioVariante::whereIn('variante_id', $varianteIds)
            ->where('lista_id', $listaId)
            ->where(function ($w) {
                $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', now()->toDateString());
            })
            ->pluck('precio', 'variante_id');

        $variantes = ProductoVariante::with(['producto' => fn ($q) => $q->select('id', 'referencia', 'nombre', 'impuesto_id')
                ->with('impuesto:id,porcentaje')])
            ->whereIn('id', $varianteIds)
            ->get()->keyBy('id');

        // LOG-J2 reusado · semáforo cartera para decidir si entra retenido.
        $credito = ConsultarCredito::run($cliente->id);
        $totalEstimado = collect($data['items'])->sum(fn ($i) =>
            (int) $i['cantidad'] * (float) ($precios[$i['variante_id']] ?? 0));
        $motivos = [];
        if ($credito['tiene_mora_critica']) {
            $motivos[] = "mora crítica ({$credito['dias_mora_max']} días)";
        }
        if ($credito['facturas_vencidas'] > 0) {
            $motivos[] = "{$credito['facturas_vencidas']} factura(s) vencida(s)";
        }
        $cupo = (float) ($credito['cupo'] ?? 0);
        if ($cupo > 0 && ($credito['saldo_cartera'] + $totalEstimado) > $cupo) {
            $excedido = round(($credito['saldo_cartera'] + $totalEstimado) - $cupo, 2);
            $motivos[] = 'cupo excedido en $' . number_format($excedido, 0);
        }
        $retenido = ! empty($motivos);

        $pedido = DB::transaction(function () use ($data, $cliente, $listaId, $precios, $variantes, $vendedor, $retenido, $motivos) {
            $numero = $this->siguienteNumero();

            // El primer vendedor que le vende a una cuenta libre se la queda.
            //   Así los 8 arrancan pudiendo trabajar (hoy ningún cliente tiene
            //   vendedor) y la cartera se va armando sola con el trabajo real,
            //   sin que gerencia tenga que repartir 400 clientes a mano.
            //   Gerencia levanta pedidos sin apropiarse de nada.
            if ($cliente->vendedor_id === null && ! $vendedor->esAracely()) {
                $cliente->forceFill(['vendedor_id' => $vendedor->id])->save();
            }

            // LOG-J4 · ruteo auto ciudad → bodega.
            $ubicOrigen = \App\Modules\Portal\Models\ReglaRuteoCiudad::resolver($cliente->ciudad);

            $ped = PedidoCliente::create([
                'numero' => $numero,
                'contacto_id' => $cliente->id,
                'vendedor_id' => $vendedor->id,
                'ubicacion_origen_id' => $ubicOrigen,
                'lista_precios_id' => $listaId,
                'estado' => $retenido ? 'retenido' : 'enviado',
                'motivo_retencion' => $retenido ? implode(' · ', $motivos) : null,
                'notas_cliente' => $data['notas'] ?? null,
                'notas_internas' => 'Pedido levantado por vendedor en terreno · ' . $vendedor->name,
                'enviado_at' => now(),
                'subtotal' => 0, 'iva' => 0, 'total' => 0,
            ]);

            $subtotal = 0; $iva = 0; $lineasValidas = 0;
            foreach ($data['items'] as $it) {
                $var = $variantes[$it['variante_id']] ?? null;
                $precio = (float) ($precios[$it['variante_id']] ?? 0);
                if (! $var || $precio <= 0) continue;

                $cantidad = (int) $it['cantidad'];
                $ivaPct = (float) ($var->producto?->impuesto?->porcentaje ?? 0);
                $lineaSub = round($precio * $cantidad, 2);
                $lineaIva = round($lineaSub * ($ivaPct / 100), 2);
                $lineasValidas++;

                PedidoClienteItem::create([
                    'pedido_id' => $ped->id,
                    'variante_id' => $var->id,
                    'producto_id' => $var->producto_id,
                    'sku_snapshot' => $var->codigo_barras ?: ($var->producto?->referencia . '-' . $var->id),
                    'descripcion_snapshot' => trim(($var->producto?->nombre ?? '') . ' · ' . ($var->color_nombre ?? '') . ' ' . ($var->talla ?? '')),
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'iva_porcentaje' => $ivaPct,
                    'subtotal' => $lineaSub,
                    'iva_valor' => $lineaIva,
                    'total' => round($lineaSub + $lineaIva, 2),
                ]);

                $subtotal += $lineaSub;
                $iva += $lineaIva;
            }

            abort_if($lineasValidas === 0, 422, 'Ninguna línea válida (sin precio en esta lista).');

            $ped->update([
                'subtotal' => round($subtotal, 2),
                'iva' => round($iva, 2),
                'total' => round($subtotal + $iva, 2),
            ]);

            return $ped;
        });

        // Alerta Gerencia si quedó retenido (misma lógica LOG-J3).
        if ($pedido->estado === 'retenido') {
            \App\Models\NotificacionErp::crear([
                'tipo' => 'pedido_retenido_cartera',
                'titulo' => "Pedido {$pedido->numero} RETENIDO · levantado por {$vendedor->name}",
                'mensaje' => $pedido->motivo_retencion ?: 'Semáforo cartera lo bloqueó.',
                'color' => 'warning',
                'icono' => 'heroicon-o-pause-circle',
                'url' => "/app/pedidos-b2b/{$pedido->id}",
            ]);
        }

        return redirect()->route('app.vendedor.index')->with(
            $pedido->estado === 'retenido' ? 'warning' : 'success',
            "Pedido {$pedido->numero} registrado para {$cliente->razon_social}."
            . ($pedido->estado === 'retenido' ? ' RETENIDO por cartera.' : '')
        );
    }

    /**
     * La cuenta tiene dueño y no soy yo → 403.
     *
     * `pedidos_cliente.vendedor_id` registraba quién levantó el pedido, pero el
     * cliente no tenía dueño: con la URL del cliente de un compañero cualquiera
     * de los 8 vendedores le levantaba el pedido y la comisión salía a su
     * nombre. Gerencia pasa siempre (tiene que poder levantar por teléfono).
     */
    private function autorizarCuenta(Contacto $cliente, Request $r): void
    {
        abort_unless(
            $cliente->esTrabajablePor($r->user()),
            403,
            'Esta cuenta es de otro vendedor. Pedile a gerencia que te la reasigne.'
        );
    }

    private function siguienteNumero(): string
    {
        $prefijo = 'PB-' . now()->format('ymd') . '-';
        $ultimo = PedidoCliente::where('numero', 'like', $prefijo . '%')
            ->lockForUpdate()->orderByDesc('id')->value('numero');
        $sig = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;
        return $prefijo . str_pad((string) $sig, 4, '0', STR_PAD_LEFT);
    }
}
