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
     * Payload: { items: [{variante_id, cantidad}], notas: '...' }
     * Congela precios desde la lista del cliente en el momento de la confirmación.
     */
    public function confirmar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.variante_id' => ['required', 'integer', 'exists:producto_variantes,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $cliente = $request->user('cliente');
        $listaId = (int) ($cliente->lista_precios_id ?? 0);
        abort_if(! $listaId, 422, 'Cliente sin lista de precios asignada.');

        $varianteIds = collect($data['items'])->pluck('variante_id')->unique()->all();
        $precios = PrecioVariante::whereIn('variante_id', $varianteIds)
            ->where('lista_id', $listaId)
            ->where(function ($w) {
                $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', now()->toDateString());
            })
            ->pluck('precio', 'variante_id')
            ->all();

        // C-QA-D-4: cargar impuesto del producto para calcular IVA real (antes era 0 fijo).
        // También filtrar solo variantes de productos activos (evasión de reglas comerciales).
        $variantes = ProductoVariante::with(['producto' => fn ($q) => $q->select('id', 'referencia', 'nombre', 'impuesto_id')
                ->with('impuesto:id,porcentaje')])
            ->whereIn('id', $varianteIds)
            ->whereHas('producto', fn ($q) => $q->where('activo', true))
            ->get()
            ->keyBy('id');

        $pedido = DB::transaction(function () use ($data, $cliente, $listaId, $precios, $variantes) {
            // C-QA-D-2: consecutivo diario con lock para evitar colisiones random_int.
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
                $var = $variantes[$it['variante_id']] ?? null;
                $precio = (float) ($precios[$it['variante_id']] ?? 0);
                if (! $var || $precio <= 0) continue;

                $cantidad = (int) $it['cantidad'];
                $lineaSub = round($precio * $cantidad, 2);
                // C-QA-D-4: IVA real del producto (antes hardcoded a 0).
                $ivaPct = (float) ($var->producto?->impuesto?->porcentaje ?? 0);
                $lineaIva = round($lineaSub * ($ivaPct / 100), 2);
                $lineasValidas++;

                PedidoClienteItem::create([
                    'pedido_id' => $ped->id,
                    'variante_id' => $var->id,
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

            // C-QA-D: rechazar pedido si TODAS las líneas fueron descartadas (sin precio / inactivas).
            if ($lineasValidas === 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Ninguna de las variantes tiene precio disponible en tu lista. Contacta al comercial.',
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
