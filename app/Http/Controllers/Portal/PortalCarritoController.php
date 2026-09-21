<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
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

        $pedido = DB::transaction(function () use ($data, $cliente, $listaId, $precios, $variantes, $productosAgg) {
            $numero = $this->siguienteNumero();

            $ped = PedidoCliente::create([
                'numero' => $numero,
                'contacto_id' => $cliente->id,
                'lista_precios_id' => $listaId,
                'estado' => 'enviado',
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

        return redirect()->route('portal.pedidos.show', $pedido->id)
            ->with('success', 'Pedido ' . $pedido->numero . ' enviado. Nuestro equipo lo revisará y facturará.');
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
