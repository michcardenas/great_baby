<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class FacturasController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                abort_unless($r->user()?->esAracely(), 403);
                return $next($r);
            }),
        ];
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $filtro = (string) $request->input('filtro', 'todas'); // todas | vencidas | pagadas | pendientes
        $pagina = max(1, (int) $request->input('page', 1));

        $query = FacturaVenta::query()->with('contacto:id,nombre_completo,telefono');

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('numero', 'like', "%{$q}%")
                    ->orWhereHas('contacto', fn ($c) => $c->where('nombre_completo', 'like', "%{$q}%")->orWhere('numero_documento', 'like', "%{$q}%"));
            });
        }

        $hoy = today('America/Bogota');
        match ($filtro) {
            'vencidas' => $query->whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])->where('fecha_vencimiento', '<', $hoy),
            'pagadas' => $query->where('estado', EstadoFactura::Pagada),
            'pendientes' => $query->whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada]),
            default => null,
        };

        $paginado = $query->orderByDesc('fecha_emision')->orderByDesc('id')->paginate(25);

        return Inertia::render('Cartera/Facturas/Index', [
            'facturas' => $paginado->through(fn ($f) => [
                'id' => $f->id,
                'numero' => $f->numero,
                'contacto' => $f->contacto?->nombre_completo ?? '—',
                'contacto_id' => $f->contacto_id,
                'telefono' => $f->contacto?->telefono,
                'fecha_emision' => $f->fecha_emision?->toDateString(),
                'fecha_vencimiento' => $f->fecha_vencimiento?->toDateString(),
                'total' => (float) $f->total,
                'saldo' => (float) $f->saldo,
                'estado' => $f->estado?->value ?? (string) $f->estado,
                'estado_label' => is_object($f->estado) && method_exists($f->estado, 'label') ? $f->estado->label() : (string) ($f->estado?->value ?? $f->estado),
                'estado_color' => is_object($f->estado) && method_exists($f->estado, 'color') ? $f->estado->color() : 'gray',
                'siigo_id' => $f->siigo_id,
                'dias_mora' => $f->fecha_vencimiento ? max(0, (int) $f->fecha_vencimiento->diffInDays($hoy, absolute: false)) : 0,
            ])->withQueryString(),
            'filtros' => ['q' => $q, 'filtro' => $filtro],
            'totales' => [
                'todas' => FacturaVenta::count(),
                'vencidas' => FacturaVenta::whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])->where('fecha_vencimiento', '<', $hoy)->count(),
                'pagadas' => FacturaVenta::where('estado', EstadoFactura::Pagada)->count(),
                'pendientes' => FacturaVenta::whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])->count(),
            ],
        ]);
    }

    public function show(FacturaVenta $factura): Response
    {
        $factura->load(['contacto', 'items.variante.producto', 'pagos.registrador']);

        return Inertia::render('Cartera/Facturas/Show', [
            'factura' => [
                'id' => $factura->id,
                'numero' => $factura->numero,
                'fecha_emision' => $factura->fecha_emision?->toDateString(),
                'fecha_vencimiento' => $factura->fecha_vencimiento?->toDateString(),
                'subtotal' => (float) $factura->subtotal,
                'descuento' => (float) $factura->descuento,
                'impuestos' => (float) $factura->impuestos,
                'total' => (float) $factura->total,
                'saldo' => (float) $factura->saldo,
                'estado' => $factura->estado?->value ?? (string) $factura->estado,
                'estado_label' => is_object($factura->estado) && method_exists($factura->estado, 'label') ? $factura->estado->label() : (string) $factura->estado,
                'estado_color' => is_object($factura->estado) && method_exists($factura->estado, 'color') ? $factura->estado->color() : 'gray',
                'ari_factura_id' => $factura->ari_factura_id,
                'siigo_id' => $factura->siigo_id,
                'numero_siigo' => $factura->numero_siigo,
                'cufe' => $factura->cufe,
                'stamp_status' => $factura->stamp_status,
                'qr_url' => $factura->qr_url,
                'token_publico' => $factura->token_publico,
                'es_electronica' => (bool) $factura->es_electronica,
                'emitida_at' => $factura->emitida_at?->toIso8601String(),
            ],
            'contacto' => $factura->contacto ? [
                'id' => $factura->contacto->id,
                'nombre' => $factura->contacto->nombre_completo,
                'documento' => trim(($factura->contacto->tipo_documento ?? '') . ' ' . ($factura->contacto->numero_documento ?? '')),
                'telefono' => $factura->contacto->telefono,
                'email' => $factura->contacto->email,
                'ciudad' => $factura->contacto->ciudad,
                'direccion' => $factura->contacto->direccion,
            ] : null,
            'items' => $factura->items->map(fn ($it) => [
                'id' => $it->id,
                'descripcion' => $it->descripcion ?? ($it->variante?->producto?->nombre ?? '—'),
                'referencia' => $it->variante?->producto?->referencia,
                'codigo_barras' => $it->variante?->codigo_barras,
                'cantidad' => (float) $it->cantidad,
                // QA-D Bloque3: columnas reales del modelo son precio_unit / descuento_pct / impuesto_pct
                // (antes leía precio_unitario / iva_porcentaje inexistentes → siempre 0 en UI).
                'precio_unitario' => (float) $it->precio_unit,
                'descuento' => (float) ($it->descuento_pct ?? 0),
                'iva_porcentaje' => (float) ($it->impuesto_pct ?? 0),
                'subtotal' => (float) $it->subtotal,
            ]),
            'pagos' => $factura->pagos->map(fn ($p) => [
                'id' => $p->id,
                'fecha' => $p->fecha?->toDateString(),
                'medio_pago' => $p->medio_pago,
                'monto_aplicado' => (float) $p->monto_aplicado,
                'monto_recibido' => (float) $p->monto_recibido,
                'referencia' => $p->referencia,
                'banco' => $p->banco,
                'registrado_por' => $p->registrador?->name,
            ]),
        ]);
    }
}
