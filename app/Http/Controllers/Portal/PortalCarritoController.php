<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Actions\ConsultarCredito;
use App\Modules\Catalogo\Models\PrecioVariante;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Portal\Models\PedidoCliente;
use App\Modules\Portal\Models\PedidoClienteItem;
use App\Modules\Portal\Actions\CrearPedidoCliente;
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
     * C-F6 · Cada item lleva variante_id (producto granular) O producto_id
     *   (producto agregado), mutuamente excluyente.
     *
     * El precio y todo lo demás lo resuelve `CrearPedidoCliente`. OJO: los
     * productos agregados todavía NO tienen precio de venta posible —falta el
     * modelo `PrecioProducto` por lista— así que sus líneas se descartan.
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

        // Toda la lógica vive en la acción compartida: el pedido que arma el
        // cliente acá y el que levanta el vendedor en la calle tienen que pasar
        // por las mismas reglas de precio, cartera y ruteo. Acá va sin vendedor
        // porque el cliente se autogestionó.
        $pedido = app(CrearPedidoCliente::class)->ejecutar(
            cliente: $request->user('cliente'),
            items: $data['items'],
            notas: $data['notas'] ?? null,
            vendedorId: null,
        );

        return redirect()->route('portal.pedidos.show', $pedido->id)
            ->with('success', $pedido->estado === 'retenido'
                ? 'Pedido '.$pedido->numero.' registrado. Está retenido por cartera; gerencia lo revisará.'
                : 'Pedido '.$pedido->numero.' enviado. Nuestro equipo lo revisará y facturará.');
    }

}
