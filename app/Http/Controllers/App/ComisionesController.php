<?php

namespace App\Http\Controllers\App;

use App\Auth\Permisos;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Crm\Actions\CalcularComisionMensual;
use App\Modules\Crm\Models\ComisionCalculada;
use App\Modules\Crm\Models\ComisionConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comisiones de vendedores: cuánto cobra cada uno y el cierre de cada mes.
 *
 * Vivía sólo en el panel Filament (un Resource de configuración y una página
 * de cierre). Al dejar `/admin` para Dropi, el ERP se quedaba sin forma de
 * liquidarle la comisión a los 8 vendedores.
 *
 * Dos permisos distintos a propósito:
 *   · `comisiones`        → correr y aprobar el cierre (Gerencia, Gerente, Contador)
 *   · `comisiones_config` → cambiar porcentajes, metas y bonos (sólo gerencia),
 *     porque eso define cuánto cobra cada persona.
 */
class ComisionesController extends Controller implements HasMiddleware
{
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            abort_unless(Permisos::puede($r->user(), 'comisiones'), 403);

            return $next($r);
        })];
    }

    public function index(Request $r): Response
    {
        $anio = (int) ($r->input('anio') ?: now()->year);
        $mes = (int) ($r->input('mes') ?: now()->month);
        $puedeConfigurar = Permisos::puede($r->user(), 'comisiones_config');

        $calculadas = ComisionCalculada::with(['vendedor:id,name'])
            ->where('anio', $anio)->where('mes', $mes)
            ->orderByDesc('total_a_pagar')->get();

        return Inertia::render('Cartera/Comisiones', [
            'periodo' => [
                'anio' => $anio,
                'mes' => $mes,
                'label' => (self::MESES[$mes] ?? '') . ' ' . $anio,
            ],
            'anios' => range(now()->year - 2, now()->year + 1),
            'meses' => collect(self::MESES)->map(fn ($n, $i) => ['valor' => $i, 'label' => $n])->values(),
            'calculadas' => $calculadas->map(fn (ComisionCalculada $c) => [
                'id' => $c->id,
                'vendedor' => $c->vendedor?->name ?? '—',
                'total_facturado' => (float) $c->total_facturado,
                'total_cobrado' => (float) $c->total_cobrado,
                'base' => (float) $c->base_comisionable,
                'porcentaje' => (float) $c->porcentaje_aplicado,
                'comision' => (float) $c->comision,
                'bono' => (float) $c->bono_meta,
                'total' => (float) $c->total_a_pagar,
                'estado' => $c->estado,
                'facturas' => is_array($c->detalle_facturas) ? count($c->detalle_facturas) : 0,
            ]),
            'total_periodo' => (float) $calculadas->sum('total_a_pagar'),
            'puede_configurar' => $puedeConfigurar,
            'configs' => $puedeConfigurar
                ? ComisionConfig::with('vendedor:id,name')->get()
                    ->map(fn (ComisionConfig $c) => [
                        'id' => $c->id,
                        'vendedor_id' => $c->vendedor_id,
                        'vendedor' => $c->vendedor?->name ?? '—',
                        'porcentaje_base' => (float) $c->porcentaje_base,
                        'cobra_solo_cobrado' => (bool) $c->cobra_solo_cobrado,
                        'meta_mensual' => (float) $c->meta_mensual,
                        'bono_por_meta_pct' => (float) $c->bono_por_meta_pct,
                        'activo' => (bool) $c->activo,
                        'notas' => $c->notas,
                    ])->values()
                : [],
            'vendedores' => $puedeConfigurar
                ? User::whereHas('roles', fn ($q) => $q->where('name', 'Vendedor'))
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->name])
                : [],
        ]);
    }

    /** Corre el cálculo del mes. Los ya aprobados o pagados no se tocan. */
    public function calcular(Request $r): RedirectResponse
    {
        $datos = $r->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $resultados = CalcularComisionMensual::run($datos['anio'], $datos['mes']);

        $yaCerrados = collect($resultados)->where('estado', 'ya_cerrado')->count();
        $ok = count($resultados) - $yaCerrados;

        return back()->with('success',
            "{$ok} vendedor(es) calculado(s)."
            . ($yaCerrados ? " {$yaCerrados} ya estaban aprobados o pagados y no se tocaron." : ''));
    }

    /** Aprueba o marca como pagada una liquidación. */
    public function cambiarEstado(Request $r, ComisionCalculada $comision): RedirectResponse
    {
        $datos = $r->validate([
            'estado' => ['required', 'in:aprobado,pagado,borrador'],
        ]);

        $destino = $datos['estado'];

        // Pagar sin aprobar antes se salta el control de quien revisa.
        if ($destino === 'pagado' && $comision->estado !== 'aprobado') {
            return back()->with('error', 'Primero hay que aprobar la liquidación y después marcarla pagada.');
        }

        // Volver a borrador una ya pagada reabriría plata que salió.
        if ($destino === 'borrador' && $comision->estado === 'pagado') {
            return back()->with('error', 'Esta liquidación ya se pagó: no se puede devolver a borrador.');
        }

        $comision->forceFill([
            'estado' => $destino,
            'aprobada_at' => $destino === 'aprobado' ? now() : $comision->aprobada_at,
            'aprobada_por' => $destino === 'aprobado' ? $r->user()->id : $comision->aprobada_por,
            'pagada_at' => $destino === 'pagado' ? now() : null,
        ])->save();

        return back()->with('success', 'Liquidación de '.($comision->vendedor?->name ?? 'vendedor')." marcada como {$destino}.");
    }

    /** Alta o edición de la configuración de un vendedor. Sólo gerencia. */
    public function guardarConfig(Request $r, ?ComisionConfig $config = null): RedirectResponse
    {
        abort_unless(Permisos::puede($r->user(), 'comisiones_config'), 403,
            'Sólo gerencia define porcentajes y metas de comisión.');

        $datos = $r->validate([
            'vendedor_id' => ['required', 'integer', 'exists:users,id'],
            'porcentaje_base' => ['required', 'numeric', 'min:0', 'max:100'],
            'cobra_solo_cobrado' => ['boolean'],
            'meta_mensual' => ['nullable', 'numeric', 'min:0'],
            'bono_por_meta_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'activo' => ['boolean'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $datos['meta_mensual'] = $datos['meta_mensual'] ?? 0;
        $datos['bono_por_meta_pct'] = $datos['bono_por_meta_pct'] ?? 0;

        // Dos configuraciones para el mismo vendedor harían que el cálculo
        // tomara una u otra según el orden de la consulta.
        $duplicada = ComisionConfig::where('vendedor_id', $datos['vendedor_id'])
            ->when($config?->exists, fn ($q) => $q->whereKeyNot($config->id))
            ->exists();

        if ($duplicada) {
            return back()->with('error', 'Ese vendedor ya tiene una configuración de comisión. Editá la que existe.');
        }

        $config?->exists ? $config->update($datos) : ComisionConfig::create($datos);

        return back()->with('success', 'Configuración de comisión guardada.');
    }

    public function eliminarConfig(Request $r, ComisionConfig $config): RedirectResponse
    {
        abort_unless(Permisos::puede($r->user(), 'comisiones_config'), 403);

        $nombre = $config->vendedor?->name ?? 'vendedor';
        $config->delete();

        return back()->with('success', "Configuración de {$nombre} eliminada. No se le calculará comisión.");
    }
}
