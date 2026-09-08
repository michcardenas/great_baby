<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ContactosController extends Controller implements HasMiddleware
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
        $rol = (string) $request->input('rol', 'todos'); // todos|cliente|b2b|proveedor|empleado|vendedor

        $query = Contacto::query();

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('nombre_completo', 'like', "%{$q}%")
                    ->orWhere('numero_documento', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        match ($rol) {
            'cliente' => $query->where('es_cliente', true),
            'b2b' => $query->where('es_cliente_b2b', true),
            'proveedor' => $query->where('es_proveedor', true),
            'empleado' => $query->where('es_empleado', true),
            'vendedor' => $query->where('es_vendedor_dropi', true),
            default => null,
        };

        $paginado = $query->orderBy('nombre_completo')->paginate(30);

        return Inertia::render('Cartera/Contactos/Index', [
            'contactos' => $paginado->through(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre_completo,
                'documento' => trim(($c->tipo_documento ?? '') . ' ' . ($c->numero_documento ?? '')),
                'telefono' => $c->telefono,
                'email' => $c->email,
                'ciudad' => $c->ciudad,
                'activo' => (bool) $c->activo,
                'roles' => array_values(array_filter([
                    $c->es_cliente ? 'Cliente' : null,
                    $c->es_cliente_b2b ? 'B2B' : null,
                    $c->es_proveedor ? 'Proveedor' : null,
                    $c->es_empleado ? 'Empleado' : null,
                    $c->es_vendedor_dropi ? 'Vendedor Dropi' : null,
                ])),
            ])->withQueryString(),
            'filtros' => ['q' => $q, 'rol' => $rol],
            'totales' => [
                'todos' => Contacto::count(),
                'cliente' => Contacto::where('es_cliente', true)->count(),
                'b2b' => Contacto::where('es_cliente_b2b', true)->count(),
                'proveedor' => Contacto::where('es_proveedor', true)->count(),
                'empleado' => Contacto::where('es_empleado', true)->count(),
                'vendedor' => Contacto::where('es_vendedor_dropi', true)->count(),
            ],
        ]);
    }

    /** API JSON — buscador rápido (para modales/autocompletes) */
    public function buscarApi(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) return response()->json([]);
        return Contacto::where(function ($qq) use ($q) {
            $qq->where('nombre_completo', 'like', "%{$q}%")
                ->orWhere('numero_documento', 'like', "%{$q}%");
        })->limit(10)->get(['id', 'nombre_completo as nombre', 'numero_documento as documento']);
    }

    public function show(Contacto $contacto): Response
    {
        $facturas = FacturaVenta::where('contacto_id', $contacto->id)
            ->orderByDesc('fecha_emision')->limit(20)
            ->get(['id', 'numero', 'fecha_emision', 'fecha_vencimiento', 'total', 'saldo', 'estado']);

        $saldoTotal = FacturaVenta::where('contacto_id', $contacto->id)
            ->whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])->sum('saldo');

        $facturadoTotal = FacturaVenta::where('contacto_id', $contacto->id)
            ->where('estado', '!=', EstadoFactura::Anulada)->sum('total');

        return Inertia::render('Cartera/Contactos/Show', [
            'contacto' => [
                'id' => $contacto->id,
                'nombre' => $contacto->nombre_completo,
                'razon_social' => $contacto->razon_social,
                'documento' => trim(($contacto->tipo_documento ?? '') . ' ' . ($contacto->numero_documento ?? '')),
                'telefono' => $contacto->telefono,
                'email' => $contacto->email,
                'direccion' => $contacto->direccion,
                'ciudad' => $contacto->ciudad,
                'departamento' => $contacto->departamento,
                'regimen_iva' => $contacto->regimen_iva,
                'activo' => (bool) $contacto->activo,
                'roles' => [
                    'cliente' => (bool) $contacto->es_cliente,
                    'b2b' => (bool) $contacto->es_cliente_b2b,
                    'proveedor' => (bool) $contacto->es_proveedor,
                    'empleado' => (bool) $contacto->es_empleado,
                    'vendedor' => (bool) $contacto->es_vendedor_dropi,
                ],
                'siigo_id' => $contacto->siigo_id,
                'siigo_sync_at' => $contacto->siigo_sync_at?->toIso8601String(),
            ],
            'facturas' => $facturas->map(fn ($f) => [
                'id' => $f->id,
                'numero' => $f->numero,
                'fecha' => $f->fecha_emision?->toDateString(),
                'vence' => $f->fecha_vencimiento?->toDateString(),
                'total' => (float) $f->total,
                'saldo' => (float) $f->saldo,
                'estado' => $f->estado?->value ?? (string) $f->estado,
                'estado_label' => is_object($f->estado) && method_exists($f->estado, 'label') ? $f->estado->label() : (string) $f->estado,
                'estado_color' => is_object($f->estado) && method_exists($f->estado, 'color') ? $f->estado->color() : 'gray',
            ]),
            'metricas' => [
                'saldo_pendiente' => (float) $saldoTotal,
                'facturado_historico' => (float) $facturadoTotal,
                'facturas_count' => (int) FacturaVenta::where('contacto_id', $contacto->id)->count(),
            ],
        ]);
    }
}
