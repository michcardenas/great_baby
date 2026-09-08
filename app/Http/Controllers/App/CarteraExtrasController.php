<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\CobranzaRegistro;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Cartera\Models\PagoVenta;
use App\Modules\Cartera\Models\SolicitudCredito;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FIL-C · Cartera restante Vue:
 *  - Reportes cartera (edad saldos, top morosos, consignaciones, PDF)
 *  - CRUD Cobranza Registro (bitácora envíos WhatsApp/email)
 *  - CRUD Solicitud Crédito (workflow excepciones)
 *  - Movimiento Contable (drill-down)
 */
class CarteraExtrasController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            $u = $r->user();
            abort_unless($u && ($u->esAracely() || $u->hasAnyRole(['Gerente', 'Contador', 'Cobrador'])), 403);
            return $next($r);
        })];
    }

    // ---------- REPORTES CARTERA ----------
    public function reportes(): Response
    {
        $hoy = today('America/Bogota');
        $desde30 = $hoy->copy()->subDays(30);

        $totalCartera = (float) FacturaVenta::whereIn('estado', ['pendiente','abonada','vencida'])
            ->whereNull('deleted_at')->sum('saldo');

        // Edad saldos (0-30, 31-60, 61-90, 91-120, 120+)
        $edades = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '91-120' => 0, '120+' => 0];
        $vencidas = FacturaVenta::whereIn('estado', ['pendiente','abonada','vencida'])
            ->whereNull('deleted_at')->get(['saldo', 'fecha_vencimiento']);
        foreach ($vencidas as $f) {
            $dias = $hoy->diffInDays($f->fecha_vencimiento, absolute: false);
            $dias = -$dias; // positivo si venció, negativo si por vencer
            $saldo = (float) $f->saldo;
            if ($dias <= 30) $edades['0-30'] += $saldo;
            elseif ($dias <= 60) $edades['31-60'] += $saldo;
            elseif ($dias <= 90) $edades['61-90'] += $saldo;
            elseif ($dias <= 120) $edades['91-120'] += $saldo;
            else $edades['120+'] += $saldo;
        }

        $topMorosos = DB::table('facturas_venta as f')
            ->join('contactos as c', 'c.id', '=', 'f.contacto_id')
            ->whereNull('f.deleted_at')->whereNull('c.deleted_at')
            ->whereNotIn('f.estado', ['pagada','anulada'])
            ->where('f.fecha_vencimiento', '<', $hoy)
            ->selectRaw('c.id, c.nombre_completo, c.razon_social, c.telefono, SUM(f.saldo) as saldo, COUNT(*) as facturas')
            ->groupBy('c.id','c.nombre_completo','c.razon_social','c.telefono')
            ->orderByDesc('saldo')->limit(20)->get();

        $consignaciones = PagoVenta::whereBetween('fecha', [$desde30, $hoy])
            ->selectRaw('medio_pago, COUNT(*) as n, SUM(monto_aplicado) as total')
            ->groupBy('medio_pago')->orderByDesc('total')->get();

        return Inertia::render('Cartera/Reportes', [
            'kpis' => [
                'total_cartera' => $totalCartera,
                'vencidas_count' => FacturaVenta::where('estado','vencida')->whereNull('deleted_at')->count(),
                'cobrado_30d' => (float) PagoVenta::whereBetween('fecha', [$desde30, $hoy])->sum('monto_aplicado'),
            ],
            'edades' => $edades,
            'topMorosos' => $topMorosos->map(fn ($r) => [
                'contacto_id' => (int) $r->id,
                'nombre' => $r->razon_social ?: $r->nombre_completo,
                'telefono' => $r->telefono,
                'saldo' => (float) $r->saldo,
                'facturas' => (int) $r->facturas,
            ]),
            'consignaciones' => $consignaciones->map(fn ($r) => [
                'medio' => $r->medio_pago, 'n' => (int) $r->n, 'total' => (float) $r->total,
            ]),
        ]);
    }

    // ---------- COBRANZA REGISTRO ----------
    public function cobranzasIndex(Request $request): Response
    {
        $cobros = CobranzaRegistro::with(['factura:id,numero', 'gestor:id,name'])
            ->orderByDesc('id')->paginate(50);
        return Inertia::render('Cartera/Cobranzas/Index', [
            'cobros' => $cobros->through(fn ($c) => [
                'id' => $c->id,
                'factura' => $c->factura?->numero,
                'canal' => $c->canal,
                'tramo' => $c->tramo,
                'estado' => $c->estado,
                'mensaje' => $c->mensaje,
                'gestor' => $c->gestor?->name,
                'enviado_at' => $c->enviado_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function cobranzaCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'factura_id' => ['required', 'integer', 'exists:facturas_venta,id'],
            'canal' => ['required', 'in:whatsapp,email,llamada,visita,sms'],
            'tramo' => ['required', 'string', 'max:20'],
            'mensaje' => ['required', 'string', 'max:1000'],
        ]);
        $factura = FacturaVenta::findOrFail($data['factura_id']);
        CobranzaRegistro::create([
            ...$data,
            'contacto_id' => $factura->contacto_id,
            'estado' => 'enviado',
            'gestor_id' => auth()->id(),
            'enviado_at' => now(),
        ]);
        return back()->with('success', 'Cobranza registrada.');
    }

    // ---------- SOLICITUD CRÉDITO ----------
    public function solicitudesIndex(Request $request): Response
    {
        $estado = (string) $request->input('estado', '');
        $sols = SolicitudCredito::with(['contacto:id,nombre_completo,razon_social', 'solicitadaPor:id,name'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('id')->paginate(30);

        return Inertia::render('Cartera/Solicitudes/Index', [
            'solicitudes' => $sols->through(fn ($s) => [
                'id' => $s->id,
                'contacto' => $s->contacto?->razon_social ?: $s->contacto?->nombre_completo,
                'monto_pedido' => (float) $s->monto_pedido,
                'motivo' => $s->motivo_retencion,
                'estado' => $s->estado, 'nivel' => $s->nivel_actual,
                'solicitada_por' => $s->solicitadaPor?->name,
                'creada' => $s->created_at?->format('Y-m-d H:i'),
                'resuelta' => $s->resuelta_at?->format('Y-m-d H:i'),
            ]),
            'filtro' => $estado,
        ]);
    }

    public function solicitudResolver(Request $r, int $solicitud): RedirectResponse
    {
        $data = $r->validate([
            'decision' => ['required', 'in:aprobar,rechazar,escalar'],
            'notas' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $s = SolicitudCredito::findOrFail($solicitud);
        $nuevo = ['aprobar' => 'aprobada', 'rechazar' => 'rechazada', 'escalar' => 'escalada'][$data['decision']];
        $s->update([
            'estado' => $nuevo,
            'resolucion_notas' => $data['notas'],
            'resuelta_por' => auth()->id(),
            'resuelta_at' => now(),
        ]);
        return back()->with('success', 'Solicitud ' . $nuevo);
    }

    // ---------- MOVIMIENTO CONTABLE ----------
    public function movimientos(Request $request): Response
    {
        $desde = $request->input('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now()->endOfMonth()->toDateString());
        $cuenta = trim((string) $request->input('cuenta', ''));

        $movs = MovimientoContable::whereBetween('fecha', [$desde, $hasta])
            ->when($cuenta, fn ($q) => $q->where('cuenta_puc', 'like', $cuenta . '%'))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(100);

        return Inertia::render('Cartera/Movimientos/Index', [
            'movimientos' => $movs->through(fn ($m) => [
                'id' => $m->id,
                'fecha' => $m->fecha instanceof \Carbon\Carbon ? $m->fecha->toDateString() : $m->fecha,
                'cuenta' => $m->cuenta_puc,
                'debe' => (float) $m->debe,
                'haber' => (float) $m->haber,
                'descripcion' => $m->descripcion,
                'origen' => $m->origen_type ? class_basename($m->origen_type) . '#' . $m->origen_id : '—',
            ]),
            'filtros' => ['desde' => $desde, 'hasta' => $hasta, 'cuenta' => $cuenta ?: null],
        ]);
    }
}
