<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Enums\ClasificacionDiferencia;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\PagoVenta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PagosController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                abort_unless($r->user()?->esContable(), 403);  // A1/A3 FIX · unificado con sidebar (Contador/Gerente)
                return $next($r);
            }),
        ];
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $medio = (string) $request->input('medio', 'todos');
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');

        $query = PagoVenta::query()->with(['factura:id,numero,contacto_id', 'factura.contacto:id,nombre_completo', 'registrador:id,name']);

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('referencia', 'like', "%{$q}%")
                    ->orWhere('banco', 'like', "%{$q}%")
                    ->orWhereHas('factura', fn ($f) => $f->where('numero', 'like', "%{$q}%"))
                    ->orWhereHas('factura.contacto', fn ($c) => $c->where('nombre_completo', 'like', "%{$q}%"));
            });
        }
        if ($medio !== 'todos') {
            $query->where('medio_pago', $medio);
        }
        if ($desde) $query->whereDate('fecha', '>=', $desde);
        if ($hasta) $query->whereDate('fecha', '<=', $hasta);

        $paginado = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(30);

        $totalAplicado = (float) (clone $query)->sum('monto_aplicado');
        $totalRecibido = (float) (clone $query)->sum('monto_recibido');

        return Inertia::render('Cartera/Pagos/Index', [
            'pagos' => $paginado->through(fn ($p) => [
                'id' => $p->id,
                'fecha' => $p->fecha?->toDateString(),
                'factura_id' => $p->factura?->id,
                'factura_numero' => $p->factura?->numero,
                'cliente' => $p->factura?->contacto?->nombre_completo,
                'medio_pago' => $p->medio_pago,
                'monto_recibido' => (float) $p->monto_recibido,
                'monto_aplicado' => (float) $p->monto_aplicado,
                'diferencia' => (float) $p->diferencia,
                'clasificacion' => $p->clasificacion_diferencia,
                'referencia' => $p->referencia,
                'banco' => $p->banco,
                'registrado_por' => $p->registrador?->name,
            ])->withQueryString(),
            'filtros' => ['q' => $q, 'medio' => $medio, 'desde' => $desde, 'hasta' => $hasta],
            'totales' => [
                'aplicado' => $totalAplicado,
                'recibido' => $totalRecibido,
                'count' => $paginado->total(),
            ],
            'mediosPago' => PagoVenta::distinct()->orderBy('medio_pago')->pluck('medio_pago')->filter()->values()->all(),
            'clasificaciones' => collect(ClasificacionDiferencia::cases())
                ->map(fn ($c) => ['valor' => $c->value, 'label' => $c->label()])->all(),
            // Catálogo real de métodos (lo mantiene /app/cartera/metodos-pago).
            // Antes el formulario traía una lista fija en el Vue, así que
            // agregar un método en el maestro no servía de nada.
            'metodos' => \App\Modules\Cartera\Models\MetodoPago::where('activo', true)
                ->orderBy('orden')->orderBy('nombre')
                ->get(['codigo', 'nombre', 'requiere_referencia', 'requiere_banco'])
                ->map(fn ($m) => [
                    'codigo' => $m->codigo,
                    'nombre' => $m->nombre,
                    'pide_referencia' => (bool) $m->requiere_referencia,
                    'pide_banco' => (bool) $m->requiere_banco,
                ])->values(),
        ]);
    }

    /**
     * Facturas con saldo, para elegir a cuál se aplica el pago.
     * Devuelve el saldo vivo para que el formulario no deje aplicar de más.
     */
    public function facturasPendientes(Request $r): JsonResponse
    {
        $q = trim((string) $r->input('q', ''));

        $facturas = FacturaVenta::query()
            ->with('contacto:id,nombre_completo,razon_social')
            ->whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])
            ->where('saldo', '>', 0)
            ->when($q !== '', fn ($qq) => $qq->where(function ($w) use ($q) {
                $w->where('numero', 'like', "%{$q}%")
                    ->orWhereHas('contacto', fn ($c) => $c
                        ->where('nombre_completo', 'like', "%{$q}%")
                        ->orWhere('razon_social', 'like', "%{$q}%")
                        ->orWhere('numero_documento', 'like', "%{$q}%"));
            }))
            ->orderBy('fecha_vencimiento')
            ->limit(25)
            ->get(['id', 'numero', 'contacto_id', 'fecha_emision', 'fecha_vencimiento', 'total', 'saldo']);

        return response()->json($facturas->map(fn ($f) => [
            'id' => $f->id,
            'numero' => $f->numero,
            'cliente' => $f->contacto?->razon_social ?: $f->contacto?->nombre_completo,
            'vence' => $f->fecha_vencimiento?->toDateString(),
            'total' => (float) $f->total,
            'saldo' => (float) $f->saldo,
        ]));
    }

    /**
     * Registra un pago recibido de un cliente.
     *
     * Esto sólo existía en el panel Filament. Al dejar `/admin` para Dropi, el
     * ERP se quedaba sin forma de registrar plata entrando, que es de lo más
     * usado todos los días.
     *
     * El saldo de la factura y el asiento a SIIGO los maneja `PagoVentaObserver`
     * (llama `factura->recalcular()` y empuja el recibo), así que acá sólo se
     * valida y se crea.
     */
    public function guardar(Request $r): RedirectResponse
    {
        $datos = $r->validate([
            'factura_id' => ['required', 'integer', 'exists:facturas_venta,id'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'monto_recibido' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'monto_aplicado' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'medio_pago' => ['required', 'string', 'max:40'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'banco' => ['nullable', 'string', 'max:120'],
            'clasificacion_diferencia' => ['nullable', 'string',
                \Illuminate\Validation\Rule::in(array_column(ClasificacionDiferencia::cases(), 'value'))],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [
            'fecha.before_or_equal' => 'No se puede registrar un pago con fecha futura.',
        ]);

        $pago = DB::transaction(function () use ($datos, $r) {
            // Bloqueo de fila: dos personas registrando el mismo pago al tiempo
            // dejarían la factura sobrepagada sin que nadie se diera cuenta.
            $factura = FacturaVenta::whereKey($datos['factura_id'])->lockForUpdate()->firstOrFail();

            abort_if($factura->estado === EstadoFactura::Anulada, 422,
                'Esa factura está anulada: no se le pueden aplicar pagos.');

            $aplicado = round((float) $datos['monto_aplicado'], 2);
            abort_if($aplicado > (float) $factura->saldo + 0.01, 422,
                'Estás aplicando $'.number_format($aplicado, 0).' a una factura que debe $'
                .number_format((float) $factura->saldo, 0).'. Bajá el monto aplicado.');

            $recibido = round((float) $datos['monto_recibido'], 2);
            $diferencia = round($recibido - $aplicado, 2);

            return PagoVenta::create([
                'factura_id' => $factura->id,
                'contacto_id' => $factura->contacto_id,
                'fecha' => $datos['fecha'],
                'monto_recibido' => $recibido,
                'monto_aplicado' => $aplicado,
                'diferencia' => $diferencia,
                'clasificacion_diferencia' => abs($diferencia) < 0.01
                    ? null
                    : ($datos['clasificacion_diferencia'] ?? ClasificacionDiferencia::NoIdentificado->value),
                'medio_pago' => $datos['medio_pago'],
                'referencia' => $datos['referencia'] ?? null,
                'banco' => $datos['banco'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'registrado_por' => $r->user()->id,
            ]);
        });

        $factura = $pago->factura()->first();

        return back()->with('success',
            'Pago registrado en '.$factura->numero.'. Saldo ahora: $'
            .number_format((float) $factura->saldo, 0, ',', '.').'.');
    }
}
