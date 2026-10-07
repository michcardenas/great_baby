<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Actions\ConsultarCredito;
use App\Modules\Catalogo\Models\PrecioVariante;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Portal\Models\PedidoCliente;
use App\Modules\Portal\Models\PedidoClienteItem;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PortalCarritoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:cliente')];
    }

    /** GET /portal/carrito — el carrito se maneja en cliente (sessionStorage). Aquí sólo renderiza vista. */
    public function index(Request $request): Response
    {
        return Inertia::render('Portal/Carrito');
    }

    /**
     * POST /portal/carrito/confirmar
     * Payload: { items: [{variante_id?, producto_id?, cantidad}], notas: '...' }
     *
     * C-F6 · Cada item lleva variante_id (producto granular) O producto_id (producto
     *   agregado), mutuamente excluyente. Precio del granular sale de PrecioVariante,
     *   del agregado sale de precio_proveedor del producto (hasta que M8 tenga
     *   PrecioProducto por lista_id).
     */
    public function confirmar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.variante_id' => ['nullable', 'integer', 'exists:producto_variantes,id', 'required_without:items.*.producto_id'],
            'items.*.producto_id' => ['nullable', 'integer', 'exists:productos,id', 'required_without:items.*.variante_id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $cliente = $request->user('cliente');
        $listaId = (int) ($cliente->lista_precios_id ?? 0);
        abort_if(! $listaId, 422, 'Cliente sin lista de precios asignada.');

        // Split de items granulares vs agregados.
        $itemsGran = collect($data['items'])->filter(fn ($i) => ! empty($i['variante_id']))->values();
        $itemsAgg  = collect($data['items'])->filter(fn ($i) => empty($i['variante_id']) && ! empty($i['producto_id']))->values();

        // Precios granulares (por variante desde PrecioVariante).
        $varianteIds = $itemsGran->pluck('variante_id')->unique()->all();
        $precios = $varianteIds
            ? PrecioVariante::whereIn('variante_id', $varianteIds)
                ->where('lista_id', $listaId)
                ->where(function ($w) {
                    $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', now()->toDateString());
                })
                ->pluck('precio', 'variante_id')
                ->all()
            : [];

        $variantes = $varianteIds
            ? ProductoVariante::with(['producto' => fn ($q) => $q->select('id', 'referencia', 'nombre', 'impuesto_id', 'desglose_stock')
                    ->with('impuesto:id,porcentaje')])
                ->whereIn('id', $varianteIds)
                ->whereHas('producto', fn ($q) => $q->where('activo', true)->where('desglose_stock', true))
                ->get()
                ->keyBy('id')
            : collect();

        // Productos agregados (validar que son realmente agregados y activos).
        $productoIds = $itemsAgg->pluck('producto_id')->unique()->all();
        $productosAgg = $productoIds
            ? \App\Modules\Dropi\Models\Producto::with('impuesto:id,porcentaje')
                ->whereIn('id', $productoIds)
                ->where('activo', true)
                ->where('desglose_stock', false)
                ->get()
                ->keyBy('id')
            : collect();

        // LOG-J2 · Enganche cartera · aquí decidimos el estado inicial.
        //   Antes: todo pedido pasaba a 'enviado' y gerencia tenía que leer la
        //   cartera del cliente a mano para decidir si lo aprobaba. Ahora el
        //   semáforo decide: si hay mora crítica (>90 días), facturas vencidas
        //   o cupo excedido, el pedido queda 'retenido' con el motivo visible,
        //   y gerencia lo libera o rechaza en su bandeja (LOG-J3).
        //   Umbrales editables via settings para que la cartera los calibre.
        $credito = ConsultarCredito::run($cliente->id);
        $totalEstimado = collect($data['items'])->sum(fn ($i) =>
            (int) $i['cantidad'] * (float) ($precios[$i['variante_id'] ?? 0] ?? 0));
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

        $pedido = DB::transaction(function () use ($data, $cliente, $listaId, $precios, $variantes, $productosAgg, $retenido, $motivos) {
            $numero = $this->siguienteNumero();

            // LOG-J4 · ruteo automático · si hay regla para la ciudad del
            //   cliente, el pedido nace apuntando a esa bodega origen y la
            //   Cola Jorge lo filtra para la sede correcta.
            $ubicOrigen = \App\Modules\Portal\Models\ReglaRuteoCiudad::resolver($cliente->ciudad);

            $ped = PedidoCliente::create([
                'numero' => $numero,
                'contacto_id' => $cliente->id,
                'lista_precios_id' => $listaId,
                'ubicacion_origen_id' => $ubicOrigen,
                'estado' => $retenido ? 'retenido' : 'enviado',
                'motivo_retencion' => $retenido ? implode(' · ', $motivos) : null,
                'notas_cliente' => $data['notas'] ?? null,
                'enviado_at' => now(),
                'subtotal' => 0, 'iva' => 0, 'total' => 0,
            ]);

            $subtotal = 0; $iva = 0; $lineasValidas = 0;
            foreach ($data['items'] as $it) {
                $cantidad = (int) $it['cantidad'];
                $ivaItem = null;

                if (! empty($it['variante_id'])) {
                    // Línea granular.
                    $var = $variantes[$it['variante_id']] ?? null;
                    $precio = (float) ($precios[$it['variante_id']] ?? 0);
                    if (! $var || $precio <= 0) continue;
                    $ivaPct = (float) ($var->producto?->impuesto?->porcentaje ?? 0);
                    $ivaItem = [
                        'variante_id' => $var->id,
                        'producto_id' => $var->producto_id,
                        'sku' => $var->codigo_barras ?: ($var->producto?->referencia . '-' . $var->id),
                        'desc' => trim(($var->producto?->nombre ?? '') . ' · ' . ($var->color_nombre ?? '') . ' ' . ($var->talla ?? '')),
                        'precio' => $precio, 'ivaPct' => $ivaPct,
                    ];
                } elseif (! empty($it['producto_id'])) {
                    // Línea agregada (colores surtidos).
                    $p = $productosAgg[$it['producto_id']] ?? null;
                    if (! $p) continue;

                    // Fix R5 CRÍTICO re-audit · precio_proveedor es COSTO, NO precio de venta.
                    //   Antes: caíamos al costo y el cliente B2B veía/pagaba a costo →
                    //   revenue leak inmediato. Ahora: precio real desde PrecioProducto por
                    //   lista_id (cuando exista M8); sin precio real → línea descartada
                    //   (el front bloquea agregar al carrito, esto es defensa en profundidad).
                    $precio = 0.0;
                    $modeloPrecioProducto = '\\App\\Modules\\Catalogo\\Models\\PrecioProducto';
                    if ($listaId && class_exists($modeloPrecioProducto)) {
                        $precio = (float) ($modeloPrecioProducto::query()
                            ->where('producto_id', $p->id)
                            ->where('lista_id', $listaId)
                            ->where(function ($w) {
                                $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', now()->toDateString());
                            })
                            ->value('precio') ?? 0);
                    }
                    if ($precio <= 0) continue;
                    $ivaPct = (float) ($p->impuesto?->porcentaje ?? 0);
                    $ivaItem = [
                        'variante_id' => null,
                        'producto_id' => $p->id,
                        'sku' => $p->referencia,
                        'desc' => $p->nombre . ' · colores surtidos',
                        'precio' => $precio, 'ivaPct' => $ivaPct,
                    ];
                }
                if (! $ivaItem) continue;

                $lineaSub = round($ivaItem['precio'] * $cantidad, 2);
                $lineaIva = round($lineaSub * ($ivaItem['ivaPct'] / 100), 2);
                $lineasValidas++;

                PedidoClienteItem::create([
                    'pedido_id' => $ped->id,
                    'variante_id' => $ivaItem['variante_id'],
                    'producto_id' => $ivaItem['producto_id'],
                    'sku_snapshot' => $ivaItem['sku'],
                    'descripcion_snapshot' => $ivaItem['desc'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $ivaItem['precio'],
                    'iva_porcentaje' => $ivaItem['ivaPct'],
                    'subtotal' => $lineaSub,
                    'iva_valor' => $lineaIva,
                    'total' => round($lineaSub + $lineaIva, 2),
                ]);

                $subtotal += $lineaSub;
                $iva += $lineaIva;
            }

            if ($lineasValidas === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Ninguno de los productos tiene precio disponible en tu lista. Contacta al comercial.',
                ]);
            }

            $ped->update([
                'subtotal' => round($subtotal, 2),
                'iva' => round($iva, 2),
                'total' => round($subtotal + $iva, 2),
            ]);

            return $ped;
        });

        // QA-D Bloque3: invalidar KPIs del cliente (dashboard portal).
        \Illuminate\Support\Facades\Cache::forget("portal.kpis.{$cliente->id}");

        // LOG-J3 · Si el semáforo retuvo el pedido, Gerencia necesita saberlo
        //   para liberarlo o rechazarlo. Un broadcast (user_id=null) hace
        //   sonar la bell a todos los admins — es lo mismo que ya hacemos
        //   para timbrado rechazado y recepciones huérfanas.
        if ($pedido->estado === 'retenido') {
            \App\Models\NotificacionErp::crear([
                'tipo' => 'pedido_retenido_cartera',
                'titulo' => "Pedido {$pedido->numero} RETENIDO · requiere Gerencia",
                'mensaje' => $pedido->motivo_retencion ?: 'El semáforo de cartera lo bloqueó.',
                'color' => 'warning',
                'icono' => 'heroicon-o-pause-circle',
                'url' => "/app/pedidos-b2b/{$pedido->id}",
            ]);
        }

        return redirect()->route('portal.pedidos.show', $pedido->id)
            ->with('success', $pedido->estado === 'retenido'
                ? 'Pedido ' . $pedido->numero . ' registrado. Está retenido por cartera; gerencia lo revisará.'
                : 'Pedido ' . $pedido->numero . ' enviado. Nuestro equipo lo revisará y facturará.');
    }

    /**
     * Consecutivo diario PB-YYMMDD-#### atómico. Reemplaza random_int(1,9999) que colisiona.
     * Corre dentro de una transacción que ya está abierta.
     */
    private function siguienteNumero(): string
    {
        $prefijo = 'PB-' . now()->format('ymd') . '-';
        // MAX+1 bajo lock del último de hoy
        $ultimo = PedidoCliente::where('numero', 'like', $prefijo . '%')
            ->lockForUpdate()
            ->orderByDesc('id')->value('numero');
        $sig = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;
        return $prefijo . str_pad((string) $sig, 4, '0', STR_PAD_LEFT);
    }
}
