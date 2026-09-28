<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Actions\SiguienteConsecutivoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\NotaDebito;
use App\Modules\Siigo\Jobs\PushNotaDebitoASiigo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sprint 4 · B.2 · CRUD Vue notas débito.
 */
class NotasDebitoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                abort_unless($r->user()?->esContable(), 403);
                return $next($r);
            }),
        ];
    }

    public function index(Request $r): Response
    {
        $q = NotaDebito::with(['factura:id,numero,total,contacto_id', 'factura.contacto:id,nombre_completo']);
        if ($busca = trim((string) $r->query('q', ''))) {
            $q->where(function ($qq) use ($busca) {
                $qq->where('numero', 'like', "%$busca%")
                   ->orWhereHas('factura', fn ($ff) => $ff->where('numero', 'like', "%$busca%"));
            });
        }
        if ($r->boolean('sin_siigo')) $q->whereNull('siigo_id');

        return Inertia::render('Cartera/NotasDebito', [
            'filtros' => ['q' => $r->query('q', ''), 'sin_siigo' => $r->boolean('sin_siigo')],
            'notas' => $q->orderByDesc('id')->paginate(30)->through(fn ($n) => [
                'id' => $n->id,
                'numero' => $n->numeroCompleto(),
                'factura_numero' => $n->factura?->numero ?? '—',
                'cliente' => $n->factura?->contacto?->nombre_completo ?? '—',
                'valor' => (float) $n->valor,
                'motivo' => $n->motivo,
                'estado' => $n->estado,
                'siigo_id' => $n->siigo_id,
                'cufe' => $n->cufe,
                'emitida_at' => $n->emitida_at?->format('Y-m-d H:i'),
                'creada_at' => $n->created_at?->diffForHumans(),
            ]),
            'kpis' => [
                'total' => NotaDebito::count(),
                'sin_siigo' => NotaDebito::whereNull('siigo_id')->count(),
                'monto_mes' => (float) NotaDebito::where('created_at', '>=', now()->startOfMonth())->sum('valor'),
            ],
        ]);
    }

    public function crear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'factura_id' => ['required', 'integer', 'exists:facturas_venta,id'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        FacturaVenta::findOrFail($data['factura_id']);
        $prefijoBase = strtoupper(trim((setting('empresa.prefijo_nd_dian') ?: 'ND') . '-'));
        $rangoNd = (int) setting('empresa.rango_hasta_nd', 0) ?: null;
        $numeroCompleto = SiguienteConsecutivoFactura::run($prefijoBase, 4, $rangoNd);
        $numeroSecuencial = (int) preg_replace('/\D/', '', substr($numeroCompleto, strlen($prefijoBase)));
        $nd = NotaDebito::create([
            'prefijo' => rtrim($prefijoBase, '-'),
            'numero' => $numeroSecuencial,
            'factura_id' => $data['factura_id'],
            'motivo' => $data['motivo'],
            'valor' => $data['valor'],
            'estado' => 'borrador',
        ]);
        return back()->with('flash', ['type' => 'success', 'message' => "ND {$nd->numeroCompleto()} creada · encolada a SIIGO."]);
    }

    public function reenviarSiigo(NotaDebito $notaDebito): RedirectResponse
    {
        PushNotaDebitoASiigo::dispatchManual($notaDebito->id);
        return back()->with('flash', ['type' => 'success', 'message' => "ND {$notaDebito->numeroCompleto()} encolada a SIIGO."]);
    }
}
