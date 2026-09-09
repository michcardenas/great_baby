<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Gerencia\Models\GastoOperativo;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M10 · Gastos operativos y reembolsos con aprobación
 */
class GastosController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth')];
    }

    public function index(Request $request): Response
    {
        $tipo = (string) $request->input('tipo', '');
        $estado = (string) $request->input('estado', '');
        $u = $request->user();
        // Re-audit M5 R2 SEG-B2 · esContable() unificado.
        $esAdmin = $u->esContable();

        $q = GastoOperativo::with(['solicita:id,name', 'aprueba:id,name'])
            ->when(! $esAdmin, fn ($qb) => $qb->where('solicita_id', $u->id))
            ->when($tipo, fn ($qb) => $qb->where('tipo', $tipo))
            ->when($estado, fn ($qb) => $qb->where('estado', $estado))
            ->orderByDesc('id')->paginate(30);

        $conteos = GastoOperativo::selectRaw('estado, COUNT(*) as total, SUM(monto) as suma')
            ->when(! $esAdmin, fn ($qb) => $qb->where('solicita_id', $u->id))
            ->groupBy('estado')->get()->keyBy('estado');

        return Inertia::render('Gastos/Index', [
            'gastos' => $q->through(fn ($g) => [
                'id' => $g->id, 'numero' => $g->numero,
                'fecha' => $g->fecha?->toDateString(),
                'categoria' => $g->categoria, 'descripcion' => $g->descripcion,
                'monto' => (float) $g->monto,
                'tipo' => $g->tipo, 'estado' => $g->estado,
                'metodo_pago' => $g->metodo_pago,
                'proveedor' => $g->proveedor, 'factura_ref' => $g->factura_ref,
                'solicita' => $g->solicita?->name, 'aprueba' => $g->aprueba?->name,
                'notas' => $g->notas,
            ]),
            'conteos' => $conteos->map(fn ($c) => ['total' => (int) $c->total, 'suma' => (float) $c->suma]),
            'esAdmin' => $esAdmin,
            'filtros' => ['tipo' => $tipo ?: null, 'estado' => $estado ?: null],
        ]);
    }

    public function crear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'fecha' => ['required', 'date'],
            'categoria' => ['required', 'string', 'max:60'],
            'descripcion' => ['required', 'string', 'max:200'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'proveedor' => ['nullable', 'string', 'max:200'],
            'factura_ref' => ['nullable', 'string', 'max:100'],
            'metodo_pago' => ['required', 'in:efectivo,transferencia,tarjeta,nequi,daviplata'],
            'tipo' => ['required', 'in:gasto,reembolso'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);
        $numero = 'GO-' . now()->format('ym') . '-' . str_pad((string) (GastoOperativo::whereYear('created_at', now()->year)->count() + 1), 4, '0', STR_PAD_LEFT);
        GastoOperativo::create([
            ...$data, 'numero' => $numero, 'solicita_id' => auth()->id(), 'estado' => 'pendiente',
        ]);
        return back()->with('success', "Solicitud {$numero} creada.");
    }

    public function aprobar(Request $r, int $gasto): RedirectResponse
    {
        $u = $r->user();
        abort_unless($u->esAracely() || $u->hasAnyRole(['Gerente']), 403);
        GastoOperativo::findOrFail($gasto)->update([
            'estado' => 'aprobado', 'aprueba_id' => $u->id,
        ]);
        return back()->with('success', 'Gasto aprobado.');
    }

    public function rechazar(Request $r, int $gasto): RedirectResponse
    {
        $u = $r->user();
        abort_unless($u->esAracely() || $u->hasAnyRole(['Gerente']), 403);
        $data = $r->validate(['motivo' => ['required', 'string', 'min:10', 'max:500']]);
        $g = GastoOperativo::findOrFail($gasto);
        $g->update([
            'estado' => 'rechazado', 'aprueba_id' => $u->id,
            'notas' => trim(($g->notas ?: '') . "\n[RECHAZO] " . $data['motivo']),
        ]);
        return back()->with('success', 'Gasto rechazado.');
    }

    public function marcarPagado(Request $r, int $gasto): RedirectResponse
    {
        $u = $r->user();
        // Re-audit M5 R2 SEG-B2 · esContable().
        abort_unless($u?->esContable(), 403);
        $g = GastoOperativo::findOrFail($gasto);
        abort_unless($g->estado === 'aprobado', 422, 'Sólo aprobados pueden marcarse pagados.');
        $g->update(['estado' => 'pagado']);
        return back()->with('success', 'Marcado como pagado.');
    }
}
