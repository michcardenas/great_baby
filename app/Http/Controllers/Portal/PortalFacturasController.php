<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\FacturaVenta;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Router;
use Inertia\Inertia;
use Inertia\Response;

class PortalFacturasController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:cliente')];
    }

    /**
     * Re-audit UX#1 · Descargar PDF de factura desde el portal. Scoped a
     * las facturas del cliente autenticado (evita IDOR por ID enumerable).
     */
    public function pdf(Request $request, FacturaVenta $factura)
    {
        $cliente = $request->user('cliente');
        abort_unless($factura->contacto_id === $cliente->id, 403);
        abort_if(in_array(
            is_object($factura->estado) ? $factura->estado->value : $factura->estado,
            ['borrador', 'anulada'], true
        ), 404, 'Factura no disponible.');

        return app(\App\Http\Controllers\Cartera\FacturaPdfController::class)
            ->publicaComoCliente($factura);
    }

    /**
     * H2 · Show del portal: detalle de una factura con ítems, pagos y saldo.
     * Scoped al cliente autenticado (idéntico guard que pdf()).
     */
    public function show(Request $request, FacturaVenta $factura): Response
    {
        $cliente = $request->user('cliente');
        abort_unless($factura->contacto_id === $cliente->id, 403);
        abort_if(in_array(
            is_object($factura->estado) ? $factura->estado->value : $factura->estado,
            ['borrador', 'anulada'], true
        ), 404, 'Factura no disponible.');

        $factura->load(['items.variante.producto', 'pagos' => fn ($q) => $q->orderBy('fecha')]);

        return Inertia::render('Portal/Facturas/Show', [
            'factura' => [
                'id' => $factura->id,
                'numero' => $factura->numero,
                'emision' => $factura->fecha_emision?->format('Y-m-d'),
                'vence' => $factura->fecha_vencimiento?->format('Y-m-d'),
                'estado' => is_object($factura->estado) ? $factura->estado->value : $factura->estado,
                'subtotal' => (float) $factura->subtotal,
                'descuento' => (float) $factura->descuento,
                'impuestos' => (float) $factura->impuestos,
                'total' => (float) $factura->total,
                'saldo' => (float) $factura->saldo,
                'es_electronica' => (bool) $factura->es_electronica,
                'cufe' => $factura->cufe,
                'observaciones' => $factura->observaciones,
                'pdf_url' => route('portal.factura.pdf', $factura->id),
                'items' => $factura->items->map(fn ($it) => [
                    'descripcion' => $it->descripcion ?? $it->variante?->producto?->nombre ?? '—',
                    'referencia' => $it->variante?->codigo_barras,
                    'cantidad' => (float) $it->cantidad,
                    'precio_unit' => (float) $it->precio_unit,
                    'descuento_pct' => (float) ($it->descuento_pct ?? 0),
                    'impuesto_pct' => (float) ($it->impuesto_pct ?? 0),
                    'subtotal' => (float) $it->subtotal,
                ])->values(),
                'pagos' => $factura->pagos->map(fn ($p) => [
                    'fecha' => optional($p->fecha)->format('Y-m-d'),
                    'medio' => $p->medio_pago,
                    'referencia' => $p->referencia,
                    'monto' => (float) $p->monto_aplicado,
                ])->values(),
            ],
        ]);
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
                // Re-audit UX#1 · URL para descargar PDF desde el portal.
                'pdf_url' => route('portal.factura.pdf', $f->id),
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
