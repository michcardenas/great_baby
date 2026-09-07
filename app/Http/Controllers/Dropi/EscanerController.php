<?php

namespace App\Http\Controllers\Dropi;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Events\PedidoDespachado;
use App\Modules\Dropi\Jobs\NotificarClienteDespachoJob;
use App\Modules\Dropi\Models\DropiEstadoBitacora;
use App\Modules\Dropi\Models\DropiPedido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EscanerController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $u = auth()->user();
            abort_unless($u && ($u->esAracely() || $u->hasAnyRole(['Alistador', 'Gerente'])), 403,
                'Solo Aracely, Alistadores y Gerencia pueden operar el escáner.');
            return $next($request);
        });
    }

    public function analizar(Request $request): JsonResponse
    {
        $guia = trim($request->input('guia', ''));
        if ($guia === '') {
            return response()->json(['accion' => 'no_encontrada', 'titulo' => 'Guía vacía', 'color' => 'gray'], 200);
        }

        $pedido = DropiPedido::where('guia', $guia)->first();

        if (! $pedido) {
            return response()->json([
                'accion' => 'no_encontrada',
                'guia' => $guia,
                'titulo' => '❓ Guía no encontrada',
                'detalle' => 'No hay pedido registrado con esta guía.',
                'color' => 'gray',
            ]);
        }

        $ctx = [
            'cliente' => $pedido->cliente_nombre,
            'ciudad' => $pedido->cliente_ciudad,
            'estado' => $pedido->estado->label(),
            'monto' => (float) $pedido->monto_esperado_proveedor,
        ];

        $recienDespachado = $pedido->despachado_at && $pedido->despachado_at->isSameDay(now());

        if (in_array($pedido->estado, [EstadoPedidoDropi::Despachado, EstadoPedidoDropi::Entregado, EstadoPedidoDropi::Pagado], true)) {
            if ($recienDespachado) {
                return response()->json([
                    'accion' => 'doble_escaneo', 'pedido_id' => $pedido->id, 'guia' => $guia,
                    'titulo' => '⚠️ Doble escaneo bloqueado',
                    'detalle' => 'Esta guía ya se escaneó como despachada hoy a las ' . $pedido->despachado_at->format('H:i') . '.',
                    'color' => 'warning', 'context' => $ctx,
                ]);
            }
            return response()->json([
                'accion' => 'proponer_devolucion', 'pedido_id' => $pedido->id, 'guia' => $guia,
                'titulo' => '↩️ ¿Es una devolución?',
                'detalle' => 'Esta guía salió el ' . $pedido->despachado_at?->format('Y-m-d') . '. Confirma si volvió a bodega.',
                'color' => 'info', 'context' => $ctx,
            ]);
        }

        return response()->json([
            'accion' => 'despachar', 'pedido_id' => $pedido->id, 'guia' => $guia,
            'titulo' => '✅ Listo para despachar',
            'detalle' => "Cliente: {$ctx['cliente']} · {$ctx['ciudad']}",
            'color' => 'success', 'context' => $ctx,
        ]);
    }

    public function despachar(Request $request): JsonResponse
    {
        $pedido = DropiPedido::find($request->input('pedido_id'));
        if (! $pedido) {
            return response()->json(['ok' => false, 'mensaje' => 'Pedido no encontrado.'], 404);
        }

        $anterior = $pedido->estado->value;
        $pedido->update([
            'estado' => EstadoPedidoDropi::Despachado,
            'despachado_at' => now(),
        ]);

        DropiEstadoBitacora::create([
            'pedido_id' => $pedido->id,
            'estado_desde' => $anterior,
            'estado_hasta' => EstadoPedidoDropi::Despachado->value,
            'fuente' => 'manual',
            'user_id' => auth()->id(),
            'payload' => ['origen' => 'escaner_camara'],
        ]);

        event(new PedidoDespachado($pedido));
        NotificarClienteDespachoJob::dispatch($pedido->id);

        return response()->json(['ok' => true, 'guia' => $pedido->guia]);
    }
}
