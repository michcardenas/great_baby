<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\FacturaVenta;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class PortalFacturasController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:cliente')];
    }

    public function index(Request $request): Response
    {
        $cliente = $request->user('cliente');

        // QA-D Bloque2: el cliente NO debe ver borradores ni anuladas (confusión/legal).
        $facturas = FacturaVenta::where('contacto_id', $cliente->id)
            ->whereNotIn('estado', ['borrador', 'anulada'])
            ->orderByDesc('fecha_emision')->paginate(30);

        return Inertia::render('Portal/Facturas/Index', [
            'facturas' => $facturas->through(fn ($f) => [
                'id' => $f->id,
                'numero' => $f->numero,
                'emision' => $f->fecha_emision?->format('Y-m-d'),
                'vence' => $f->fecha_vencimiento?->format('Y-m-d'),
                'total' => (float) $f->total,
                'saldo' => (float) $f->saldo,
                'estado' => is_object($f->estado) ? $f->estado->value : $f->estado,
            ]),
            'kpis' => [
                'saldo_total' => (float) FacturaVenta::where('contacto_id', $cliente->id)
                    ->whereIn('estado', ['pendiente', 'abonada', 'vencida'])->sum('saldo'),
                'vencidas' => (int) FacturaVenta::where('contacto_id', $cliente->id)
                    ->where('estado', 'vencida')->count(),
            ],
        ]);
    }
}
