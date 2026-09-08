<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\PagoVenta;
use App\Modules\Dropi\Models\GarantiaTicket;
use App\Modules\Portal\Models\PedidoCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * MEJORAS-B · Notificaciones tipo real-time via polling AJAX.
 * El cliente hace GET cada 15s con ?desde=timestamp; retorna eventos nuevos.
 */
class NotificacionesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(['auth', 'throttle:120,1'])];
    }

    public function __invoke(Request $request): JsonResponse
    {
        $desde = $request->input('desde');
        $cutoff = $desde ? \Carbon\Carbon::parse($desde) : now()->subMinutes(15);
        $eventos = [];

        // 1. Pedidos B2B nuevos
        $b2b = PedidoCliente::where('created_at', '>', $cutoff)
            ->where('estado', 'enviado')
            ->with('contacto:id,razon_social,nombre_completo')
            ->orderByDesc('id')->limit(10)->get();
        foreach ($b2b as $p) {
            $eventos[] = [
                'id' => 'b2b-' . $p->id,
                'tipo' => 'pedido_b2b',
                'titulo' => 'Nuevo pedido B2B',
                'body' => ($p->contacto?->razon_social ?: $p->contacto?->nombre_completo) . ' · $' . number_format($p->total, 0, ',', '.'),
                'url' => '/app/pedidos-b2b/' . $p->id,
                'at' => $p->created_at->toIso8601String(),
                'icon' => '📦', 'color' => 'blue',
            ];
        }

        // 2. Pagos recibidos
        $pagos = PagoVenta::where('created_at', '>', $cutoff)
            ->orderByDesc('id')->limit(10)->get();
        foreach ($pagos as $pg) {
            $eventos[] = [
                'id' => 'pago-' . $pg->id,
                'tipo' => 'pago',
                'titulo' => '💰 Pago recibido',
                'body' => '$' . number_format($pg->monto_aplicado, 0, ',', '.') . ' · ' . ($pg->medio_pago ?? ''),
                'url' => '/app/pagos',
                'at' => $pg->created_at->toIso8601String(),
                'icon' => '💰', 'color' => 'emerald',
            ];
        }

        // 3. Garantías nuevas
        $gar = GarantiaTicket::where('created_at', '>', $cutoff)
            ->orderByDesc('id')->limit(5)->get();
        foreach ($gar as $g) {
            $eventos[] = [
                'id' => 'gar-' . $g->id,
                'tipo' => 'garantia',
                'titulo' => 'Nueva garantía',
                'body' => $g->numero . ' · ' . $g->cliente_nombre,
                'url' => '/app/garantias/' . $g->id,
                'at' => $g->created_at->toIso8601String(),
                'icon' => '🛡', 'color' => 'amber',
            ];
        }

        // 4. Facturas emitidas
        $facts = FacturaVenta::where('created_at', '>', $cutoff)
            ->orderByDesc('id')->limit(5)->get();
        foreach ($facts as $f) {
            $eventos[] = [
                'id' => 'fact-' . $f->id,
                'tipo' => 'factura',
                'titulo' => 'Factura emitida',
                'body' => $f->numero . ' · $' . number_format($f->total, 0, ',', '.'),
                'url' => '/app/facturas/' . $f->id,
                'at' => $f->created_at->toIso8601String(),
                'icon' => '📄', 'color' => 'brand',
            ];
        }

        // Ordenar por fecha desc
        usort($eventos, fn ($a, $b) => strcmp($b['at'], $a['at']));

        return response()->json([
            'eventos' => $eventos,
            'ahora' => now()->toIso8601String(),
        ]);
    }
}
